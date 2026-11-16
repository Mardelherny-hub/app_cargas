# Traspaso del entorno local de app_cargas a asus02

Fecha: 16/11/2026 · Responsable: Charly (ejecuta por consola) · Aprueba: Víctor

## Objetivo

Que asus02 quede con el desarrollo **exactamente como está hoy** en la máquina actual: código, ramas, cambios sin commitear, base local, `.env`, archivos de `storage` y certificados. Desde ahí se desarrolla el plan de `docs/PLAN-IMPORTACION-VIAJES-EXISTENTES.md`.

## Por qué no alcanza con clonar el repo

Hay cosas que **no están en git**:

| Qué | Dónde | Por qué importa |
|---|---|---|
| `.env` | raíz del proyecto | Trae `APP_KEY`, y sin la misma clave no se pueden leer los datos cifrados de la base. |
| Certificados `.p12` y archivos subidos | `storage/app/private/` (disco `local`) | Están fuera de git. |
| Envíos guardados | `storage/app/envios/` | Están fuera de git. |
| Archivos públicos | `storage/app/public/` | Se publican con `storage:link`. |
| Base de datos local | MySQL, con el nombre que figura en `.env` | Tiene las empresas de prueba, entre ellas «Empresa QA Test». |
| Cambios sin commitear, stashes y ramas sin push | repo local | Los agentes pueden tener trabajo en curso. |
| Muestras del cliente | `/var/www/html/muestras_importacion/` | No van al repo porque es público. |

## Reglas

- **Nada se borra en la máquina actual** hasta que Víctor confirme que asus02 quedó bien.
- No se commitean ni se revierten cambios ajenos. Se trasladan tal cual, copiando el directorio completo.
- No se toca stage ni producción. Todo es entre máquinas locales.
- No se corren `migrate:fresh`, `db:wipe` ni seeders.
- Antes de empezar, **todos los agentes en pausa**: nadie trabaja en el repo durante el traspaso.

## Fase 1. Máquina actual: inventario y resguardo

Todo en solo lectura, salvo la creación de `~/traspaso/`.

```bash
mkdir -p ~/traspaso
cd /var/www/html/app_cargas

# 1. Inventario de git (se guarda para comparar después)
{
  echo "== status";   git status
  echo "== stash";    git stash list
  echo "== ramas";    git branch -vv
  echo "== sin push"; git log --branches --not --remotes --oneline
  echo "== HEAD";     git rev-parse --abbrev-ref HEAD; git rev-parse HEAD
} > ~/traspaso/inventario_git.txt 2>&1

# 2. Versiones del entorno
{ php -v; php -m; composer --version; node -v; npm -v; mysql --version; apache2 -v; } > ~/traspaso/versiones.txt 2>&1

# 3. Configuración de Apache del sitio local (copiar el .conf que apunta a /var/www/html/app_cargas)
grep -l "app_cargas" /etc/apache2/sites-available/*.conf | xargs -I{} cp {} ~/traspaso/
```

### Dump de la base local

Los datos de conexión salen de `.env` (`DB_DATABASE`, `DB_USERNAME`).

```bash
mysqldump --single-transaction --routines --triggers -u <DB_USERNAME> -p <DB_DATABASE> > ~/traspaso/app_cargas_local.sql
tail -1 ~/traspaso/app_cargas_local.sql   # tiene que decir "Dump completed"
```

### Conteos de control

Sirven para comparar la base en las dos máquinas.

```bash
mysql -u <DB_USERNAME> -p <DB_DATABASE> -e "SELECT 'companies',COUNT(*) FROM companies UNION ALL SELECT 'voyages',COUNT(*) FROM voyages UNION ALL SELECT 'shipments',COUNT(*) FROM shipments UNION ALL SELECT 'bills_of_lading',COUNT(*) FROM bills_of_lading UNION ALL SELECT 'manifest_imports',COUNT(*) FROM manifest_imports;" > ~/traspaso/conteos_origen.txt
```

### Paquete del proyecto completo

Incluye `.git`, `.env`, `storage` y los cambios sin commitear. `vendor` y `node_modules` se reinstalan en asus02.

```bash
tar --exclude=app_cargas/vendor --exclude=app_cargas/node_modules \
    -czpf ~/traspaso/app_cargas.tgz -C /var/www/html app_cargas

# Muestras del cliente, si ya están en la máquina
[ -d /var/www/html/muestras_importacion ] && tar -czpf ~/traspaso/muestras_importacion.tgz -C /var/www/html muestras_importacion

# Sumas de control
cd ~/traspaso && sha256sum *.tgz *.sql > SHA256SUMS
```

Se pasa la carpeta `~/traspaso/` completa a asus02, por la red local (`rsync -a` o `scp`) o por pendrive.

## Fase 2. asus02: instalación

### 1. Requisitos

Mismas versiones que `versiones.txt`. Como mínimo:
- PHP 8.2 o superior, con las extensiones `mbstring`, `xml`, `dom`, `curl`, `zip`, `gd`, `intl`, `soap`, `openssl` y `pdo_mysql`.
- MySQL o MariaDB.
- Apache con `mod_rewrite`.
- Composer, Node y npm, git y `gh`.

### 2. Verificar las sumas y descomprimir en la misma ruta

```bash
cd ~/traspaso && sha256sum -c SHA256SUMS
sudo tar -xzpf app_cargas.tgz -C /var/www/html
[ -f muestras_importacion.tgz ] && sudo tar -xzpf muestras_importacion.tgz -C /var/www/html
sudo chown -R $USER:www-data /var/www/html/app_cargas
sudo chmod -R ug+rw /var/www/html/app_cargas/storage /var/www/html/app_cargas/bootstrap/cache
```

### 3. Base de datos

Se crea con el mismo nombre, usuario y clave de `.env`.

```bash
sudo mysql -e "CREATE DATABASE <DB_DATABASE> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '<DB_USERNAME>'@'localhost' IDENTIFIED BY '<DB_PASSWORD>'; GRANT ALL ON <DB_DATABASE>.* TO '<DB_USERNAME>'@'localhost'; FLUSH PRIVILEGES;"
mysql -u <DB_USERNAME> -p <DB_DATABASE> < ~/traspaso/app_cargas_local.sql
```

Después se corre la misma consulta de conteos y se compara con `conteos_origen.txt`. Tienen que dar iguales.

### 4. Dependencias y Laravel

```bash
cd /var/www/html/app_cargas
composer install
npm ci && npm run build
php artisan storage:link
php artisan optimize:clear
php artisan migrate:status   # solo mirar
```

Si `migrate:status` muestra migraciones pendientes, **se informa antes de correrlas**.

### 5. Apache

Se copia el `.conf` de `~/traspaso/` a `/etc/apache2/sites-available/` y se activa:

```bash
sudo a2ensite <archivo>.conf && sudo a2enmod rewrite && sudo systemctl reload apache2
```

Si el sitio usa un nombre local, se agrega también en `/etc/hosts`.

### 6. Cola de trabajos

`.env` usa `QUEUE_CONNECTION=database`, así que las importaciones se procesan en la cola y sin worker no avanzan. Para desarrollar, se deja corriendo en una terminal:

```bash
php artisan queue:work
```

### 7. Git y GitHub

```bash
cd /var/www/html/app_cargas
git remote -v                # Mardelherny-hub/app_cargas
gh auth status              # o gh auth login
git fetch --all --prune
git status; git stash list; git branch -vv
```

La salida tiene que coincidir con `inventario_git.txt`: la misma rama actual, el mismo HEAD, los mismos cambios sin commitear y los mismos stashes.

### 8. Agentes

- Codex se instala en asus02 y se apunta a `/var/www/html/app_cargas`.
- Víctor instala ahí a Code, del equipo de Claudia.
- Los dos leen `AGENTS.md` del repo, así que no hace falta copiar reglas.

## Fase 3. Verificación (antes de dar por cerrado)

1. **El sitio funciona:** el sitio local abre y se entra con los usuarios de prueba (super-admin, company-admin y user de «Empresa QA Test»).
2. **Las importaciones funcionan:** en «Empresa QA Test» se importa un archivo real de un formato existente. El job lo procesa con `queue:work` corriendo, y al terminar se **revierte** desde el historial de importaciones.
3. **Los certificados están:** en la ficha de una empresa de Roberto que tenga certificado, el sistema lo encuentra. **No se envía nada a la Aduana.**
4. **Los tests pasan:**

   ```bash
   php artisan test tests/Unit/Services/Parsers
   php artisan test --filter=VoyageTransferTest
   ```

   El resultado se compara con el mismo comando corrido en la máquina actual: tienen que dar igual.

   ⚠️ **Nunca correr `php artisan test` sin ruta ni filtro.** En `phpunit.xml` la conexión sqlite en memoria está comentada, así que los tests usan la base MySQL local. Varios tests de `tests/Feature`, como `AuthenticationTest` y `DeleteAccountTest`, usan `RefreshDatabase`: **vacían la base local**. Los dos comandos de arriba son seguros: los tests de parsers solo leen o hacen rollback, y `VoyageTransferTest` usa sqlite en memoria.

5. **La base es la misma:** los conteos coinciden con los de origen.

## Devolución a Víctor

Charly informa:
- la diferencia entre `inventario_git.txt` y el estado en asus02 (debería ser ninguna);
- los conteos de origen y de destino;
- el resultado de los 5 puntos de verificación;
- lo que no se pudo verificar.

Con esa devolución, Víctor da el OK y recién ahí se retoma el trabajo, ya en asus02. La máquina anterior queda intacta como respaldo hasta nuevo aviso.
