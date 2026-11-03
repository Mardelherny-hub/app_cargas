<?php

namespace App\Services\Manual;

use App\Models\User;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerHelper;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Lee los artículos del manual y decide cuáles puede ver cada usuario.
 *
 * El contenido se cachea completo y sin filtrar; los permisos y el estado se
 * evalúan en cada lectura. La caché se limpia con optimize:clear.
 */
class ManualRepository
{
    private const CLAVE_CACHE = 'manual.contenido.v2';

    private const ROLES = ['super-admin', 'company-admin', 'user'];

    private const ROLES_EMPRESA = ['Cargas', 'Desconsolidador', 'Transbordos'];

    /**
     * Artículos visibles para el usuario, listos para el listado y el buscador.
     */
    public function indiceBusqueda(User $user): array
    {
        $indice = [];

        foreach ($this->contenido()['articulos'] as $articulo) {
            if ($this->puedeVer($user, $articulo)) {
                $indice[] = [
                    'titulo' => $articulo['titulo'],
                    'tema' => $articulo['tema'],
                    'tema_nombre' => $articulo['tema_nombre'],
                    'borrador' => $articulo['borrador'],
                    'palabras_clave' => $articulo['palabras_clave'],
                    'texto' => $articulo['texto'],
                    'url' => $this->urlArticulo($articulo['tema'], $articulo['slug']),
                ];
            }
        }

        return $indice;
    }

    /**
     * Artículo pedido, o null si no existe o el usuario no puede verlo.
     */
    public function articulo(User $user, string $tema, string $slug): ?array
    {
        $articulos = $this->contenido()['articulos'];
        $articulo = $articulos["{$tema}/{$slug}"] ?? null;

        if ($articulo === null || ! $this->puedeVer($user, $articulo)) {
            return null;
        }

        $relacionados = [];
        foreach ($articulo['relacionados'] as $clave) {
            $relacionado = $articulos[$clave] ?? null;

            if ($relacionado !== null && $this->puedeVer($user, $relacionado)) {
                $relacionados[] = [
                    'titulo' => $relacionado['titulo'],
                    'tema_nombre' => $relacionado['tema_nombre'],
                    'url' => $this->urlArticulo($relacionado['tema'], $relacionado['slug']),
                ];
            }
        }

        // Solo artículos que el usuario puede ver: los ocultos no cuentan como vecinos.
        $vecinos = [];
        foreach ($articulos as $vecino) {
            if ($vecino['tema'] === $tema && $this->puedeVer($user, $vecino)) {
                $vecinos[] = $vecino;
            }
        }
        $posicion = array_search($slug, array_column($vecinos, 'slug'), true);

        return [
            'titulo' => $articulo['titulo'],
            'tema' => $articulo['tema'],
            'tema_nombre' => $articulo['tema_nombre'],
            'borrador' => $articulo['borrador'],
            'html' => $articulo['html'],
            'secciones' => $articulo['secciones'],
            'relacionados' => $relacionados,
            'anterior' => $this->enlaceVecino($vecinos[$posicion - 1] ?? null),
            'siguiente' => $this->enlaceVecino($vecinos[$posicion + 1] ?? null),
        ];
    }

    private function enlaceVecino(?array $articulo): ?array
    {
        if ($articulo === null) {
            return null;
        }

        return [
            'titulo' => $articulo['titulo'],
            'url' => $this->urlArticulo($articulo['tema'], $articulo['slug']),
        ];
    }

    /**
     * Grupos de términos que el buscador trata como una sola palabra.
     */
    public function sinonimos(): array
    {
        return $this->contenido()['sinonimos'];
    }

    private function puedeVer(User $user, array $articulo): bool
    {
        if ($articulo['borrador'] && ! config('manual.mostrar_borradores')) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        if (! $user->hasAnyRole($articulo['roles'])) {
            return false;
        }

        if ($articulo['roles_empresa'] === []) {
            return true;
        }

        return array_intersect($articulo['roles_empresa'], $user->getCompanyRoles()) !== [];
    }

    private function contenido(): array
    {
        return Cache::rememberForever(self::CLAVE_CACHE, fn () => [
            'articulos' => $this->cargarArticulos(),
            'sinonimos' => $this->cargarSinonimos(),
        ]);
    }

    private function cargarArticulos(): array
    {
        $parser = (new FrontMatterExtension())->getFrontMatterParser();
        $leidos = [];

        // Primera pasada: hay que conocer todos los artículos antes de resolver los enlaces.
        foreach (config('manual.temas') as $tema => $temaNombre) {
            $encontrados = [];

            foreach (glob(config('manual.carpeta')."/{$tema}/*.md") ?: [] as $ruta) {
                $slug = basename($ruta, '.md');

                try {
                    $documento = $parser->parse((string) file_get_contents($ruta));
                } catch (CommonMarkException $e) {
                    $this->descartar($ruta, 'cabecera ilegible');

                    continue;
                }

                $error = $this->errorDeCabecera($documento->getFrontMatter(), $tema, $slug);

                if ($error !== null) {
                    $this->descartar($ruta, $error);

                    continue;
                }

                $encontrados[] = [
                    'tema' => $tema,
                    'tema_nombre' => $temaNombre,
                    'slug' => $slug,
                    'cabecera' => $documento->getFrontMatter(),
                    'markdown' => $documento->getContent(),
                ];
            }

            usort($encontrados, fn (array $a, array $b) => [$a['cabecera']['orden'], $a['cabecera']['titulo']]
                <=> [$b['cabecera']['orden'], $b['cabecera']['titulo']]);

            foreach ($encontrados as $encontrado) {
                $leidos["{$tema}/{$encontrado['slug']}"] = $encontrado;
            }
        }

        $convertidor = $this->convertidor(array_keys($leidos));
        $articulos = [];

        foreach ($leidos as $clave => $leido) {
            $cabecera = $leido['cabecera'];
            $convertido = $convertidor->convert($leido['markdown']);
            $html = $convertido->getContent();

            $articulos[$clave] = [
                'tema' => $leido['tema'],
                'tema_nombre' => $leido['tema_nombre'],
                'slug' => $leido['slug'],
                'titulo' => $cabecera['titulo'],
                'roles' => $cabecera['roles'],
                'roles_empresa' => $cabecera['roles_empresa'] ?? [],
                'palabras_clave' => $cabecera['palabras_clave'],
                'relacionados' => $cabecera['relacionados'] ?? [],
                // Lo verificado solo contra el código no se publica, aunque diga "publicado".
                'borrador' => $cabecera['estado'] !== 'publicado' || $cabecera['verificado']['entorno'] !== 'stage',
                'html' => $html,
                'secciones' => $this->secciones($convertido->getDocument()),
                'texto' => $this->textoPlano($html),
            ];
        }

        return $articulos;
    }

    private function errorDeCabecera(mixed $cabecera, string $tema, string $slug): ?string
    {
        if (! is_array($cabecera)) {
            return 'sin cabecera';
        }

        if (preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
            return 'nombre de archivo inválido';
        }

        if (($cabecera['tema'] ?? null) !== $tema) {
            return 'el tema no coincide con la carpeta';
        }

        if (! is_string($cabecera['titulo'] ?? null) || trim($cabecera['titulo']) === '') {
            return 'titulo inválido';
        }

        if (! is_int($cabecera['orden'] ?? null)) {
            return 'orden inválido';
        }

        if (! is_array($cabecera['roles'] ?? null) || $cabecera['roles'] === []
            || ! $this->esListaDeTextos($cabecera['roles'])
            || array_diff($cabecera['roles'], self::ROLES) !== []) {
            return 'roles inválidos';
        }

        $rolesEmpresa = $cabecera['roles_empresa'] ?? [];
        if (! is_array($rolesEmpresa) || ! $this->esListaDeTextos($rolesEmpresa)
            || array_diff($rolesEmpresa, self::ROLES_EMPRESA) !== []) {
            return 'roles_empresa inválidos';
        }

        if (! is_array($cabecera['palabras_clave'] ?? null) || ! $this->esListaDeTextos($cabecera['palabras_clave'])) {
            return 'palabras_clave inválidas';
        }

        if (! is_array($cabecera['relacionados'] ?? []) || ! $this->esListaDeTextos($cabecera['relacionados'] ?? [])) {
            return 'relacionados inválidos';
        }

        if (! in_array($cabecera['estado'] ?? null, ['borrador', 'publicado'], true)) {
            return 'estado inválido';
        }

        $verificado = $cabecera['verificado'] ?? null;
        if (! is_array($verificado) || ! isset($verificado['version'], $verificado['fecha'])
            || ! in_array($verificado['entorno'] ?? null, ['stage', 'codigo'], true)) {
            return 'verificado inválido';
        }

        return null;
    }

    private function esListaDeTextos(array $lista): bool
    {
        foreach ($lista as $elemento) {
            if (! is_string($elemento) || trim($elemento) === '') {
                return false;
            }
        }

        return true;
    }

    private function descartar(string $ruta, string $motivo): void
    {
        Log::warning('Artículo del manual descartado', ['archivo' => $ruta, 'motivo' => $motivo]);
    }

    private function convertidor(array $claves): MarkdownConverter
    {
        $entorno = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            // Solo el id en el título: un enlace vacío sería una parada de teclado sin contenido.
            'heading_permalink' => [
                'symbol' => '',
                'apply_id_to_heading' => true,
                'id_prefix' => '',
                'insert' => 'none',
            ],
        ]);
        $entorno->addExtension(new CommonMarkCoreExtension());
        $entorno->addExtension(new HeadingPermalinkExtension());
        $entorno->addEventListener(
            DocumentParsedEvent::class,
            fn (DocumentParsedEvent $evento) => $this->resolverEnlaces($evento->getDocument(), $claves)
        );

        return new MarkdownConverter($entorno);
    }

    /**
     * Traduce los enlaces manual: y ruta:. Si el destino no existe, deja solo el texto.
     */
    private function resolverEnlaces(Document $documento, array $claves): void
    {
        $enlaces = [];
        foreach ($documento->iterator() as $nodo) {
            if ($nodo instanceof Link) {
                $enlaces[] = $nodo;
            }
        }

        foreach ($enlaces as $enlace) {
            $url = $enlace->getUrl();

            if (str_starts_with($url, 'manual:')) {
                $clave = substr($url, strlen('manual:'));
                $destino = null;

                if (in_array($clave, $claves, true)) {
                    [$tema, $slug] = explode('/', $clave);
                    $destino = $this->urlArticulo($tema, $slug);
                }
            } elseif (str_starts_with($url, 'ruta:')) {
                $destino = $this->urlRuta(substr($url, strlen('ruta:')));
            } else {
                continue;
            }

            if ($destino !== null) {
                $enlace->setUrl($destino);

                continue;
            }

            foreach ([...$enlace->children()] as $hijo) {
                $enlace->insertBefore($hijo);
            }
            $enlace->detach();
        }
    }

    /**
     * URL relativa: el contenido cacheado no depende del dominio.
     */
    private function urlArticulo(string $tema, string $slug): string
    {
        return route('manual.show', ['tema' => $tema, 'slug' => $slug], false);
    }

    private function urlRuta(string $nombre): ?string
    {
        if (! Route::has($nombre)) {
            return null;
        }

        try {
            return route($nombre, [], false);
        } catch (UrlGenerationException) {
            return null;
        }
    }

    /**
     * Títulos de nivel 2 del documento ya convertido, con el id que recibieron.
     */
    private function secciones(Document $documento): array
    {
        $secciones = [];

        foreach ($documento->iterator() as $nodo) {
            if ($nodo instanceof Heading && $nodo->getLevel() === 2) {
                $id = (string) $nodo->data->get('attributes/id', null);
                $titulo = StringContainerHelper::getChildText($nodo, [RawMarkupContainerInterface::class]);

                if ($id !== '' && trim($titulo) !== '') {
                    $secciones[] = ['id' => $id, 'titulo' => $titulo];
                }
            }
        }

        return $secciones;
    }

    private function textoPlano(string $html): string
    {
        $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    private function cargarSinonimos(): array
    {
        $ruta = config('manual.carpeta').'/_sinonimos.yml';

        if (! is_file($ruta)) {
            return [];
        }

        try {
            $grupos = Yaml::parseFile($ruta);
        } catch (ParseException) {
            $this->descartar($ruta, 'archivo de sinónimos ilegible');

            return [];
        }

        if (! is_array($grupos)) {
            return [];
        }

        return array_values(array_filter($grupos, 'is_array'));
    }
}
