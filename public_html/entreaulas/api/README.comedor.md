# API del Comedor

Esta API permite acceder y gestionar los datos de menús del comedor de forma programática mediante JSON.

## Autenticación

La API utiliza el mismo sistema de autenticación que el resto de la aplicación. Todas las solicitudes deben estar autenticadas a través de la sesión PHP.

## Permisos

- **Lectura (GET)**: Requiere permiso `entreaulas:docente`
- **Escritura (POST)**: Requiere permisos `sysadmin:access` (además de `entreaulas:docente`)

## Endpoints

### 1. Obtener tipos de menú

**GET** `/entreaulas/api/comedor.php?action=get_menu_types&aulario={aulario_id}`

Devuelve todos los tipos de menú disponibles para un aulario.

**Parámetros:**
- `aulario` (requerido): ID del aulario

**Ejemplo de respuesta:**
```json
{
  "success": true,
  "menu_types": [
    {
      "id": "basal",
      "label": "Menú basal",
      "color": "#0d6efd"
    },
    {
      "id": "vegetariano",
      "label": "Menú vegetariano",
      "color": "#198754"
    },
    {
      "id": "alergias",
      "label": "Menú alergias",
      "color": "#dc3545"
    }
  ]
}
```

---

### 2. Obtener menú de un día

**GET** `/entreauals/api/comedor.php?action=get_menu&aulario={aulario_id}&date={date}&menu={menu_type_id}`

Obtiene el menú de un día específico y tipo de menú.

**Parámetros:**
- `aulario` (requerido): ID del aulario
- `date` (opcional): Fecha en formato YYYY-MM-DD (por defecto: hoy)
- `menu` (opcional): ID del tipo de menú (por defecto: primer tipo disponible)

**Ejemplo de respuesta:**
```json
{
  "success": true,
  "date": "2026-02-18",
  "menu_type": "basal",
  "menu_types": [/* lista de tipos de menú */],
  "menu": {
    "plates": {
      "primero": {
        "name": "Lentejas",
        "pictogram": ""
      },
      "segundo": {
        "name": "Pollo asado",
        "pictogram": "basal_segundo_pict.jpg"
      },
      "postre": {
        "name": "Manzana",
        "pictogram": ""
      }
    }
  }
}
```

---

### 3. Guardar menú

**POST** `/entreaulas/api/comedor.php?action=save_menu&aulario={aulario_id}`

Guarda o actualiza un menú para un día específico.

**Parámetros (JSON):**
```json
{
  "date": "2026-02-18",
  "menu_type": "basal",
  "plates": {
    "primero": {
      "name": "Lentejas"
    },
    "segundo": {
      "name": "Pollo asado"
    },
    "postre": {
      "name": "Manzana"
    }
  }
}
```

**Ejemplo de uso con curl:**
```bash
curl -X POST "http://localhost/entreaulas/api/comedor.php?action=save_menu&aulario=aulario_id" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2026-02-18",
    "menu_type": "basal",
    "plates": {
      "primero": {"name": "Sopa"},
      "segundo": {"name": "Pescado"},
      "postre": {"name": "Yogur"}
    }
  }'
```

---

### 4. Añadir nuevo tipo de menú

**POST** `/entreaulas/api/comedor.php?action=add_menu_type&aulario={aulario_id}`

Crea un nuevo tipo de menú.

**Parámetros (JSON):**
```json
{
  "id": "celiaco",
  "label": "Menú celíaco",
  "color": "#ff9800"
}
```

**Ejemplo de uso con curl:**
```bash
curl -X POST "http://localhost/entreaulas/api/comedor.php?action=add_menu_type&aulario=aulario_id" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "celiaco",
    "label": "Menú celíaco",
    "color": "#ff9800"
  }'
```

---

### 5. Renombrar tipo de menú

**POST** `/entreaulas/api/comedor.php?action=rename_menu_type&aulario={aulario_id}`

Cambia el nombre o color de un tipo de menú existente.

**Parámetros (JSON):**
```json
{
  "id": "basal",
  "label": "Menú estándar",
  "color": "#0d6efd"
}
```

---

### 6. Eliminar tipo de menú

**POST** `/entreaulas/api/comedor.php?action=delete_menu_type&aulario={aulario_id}`

Elimina un tipo de menú.

**Parámetros (JSON o form-data):**
```json
{
  "id": "celiaco"
}
```

---

## Códigos de error

| Código | Descripción |
|--------|-------------|
| `FORBIDDEN` (403) | Sin permisos suficientes |
| `INVALID_SESSION` (400) | Centro no encontrado en la sesión |
| `MISSING_PARAM` (400) | Parámetro requerido no proporcionado |
| `INVALID_FORMAT` (400) | Formato inválido (ej: fecha) |
| `INVALID_MENU_TYPE` (400) | Tipo de menú inválido |
| `DUPLICATE` (400) | El tipo de menú ya existe |
| `NOT_FOUND` (404) | Recurso no encontrado |
| `INVALID_ACTION` (400) | Acción no reconocida |

---

## Ejemplos en JavaScript

### Obtener menú actual

```javascript
async function obtenerMenu(aularioId) {
  const response = await fetch(
    `/entreaulas/api/comedor.php?action=get_menu&aulario=${aularioId}`
  );
  const data = await response.json();
  return data.menu;
}
```

### Guardar menú

```javascript
async function guardarMenu(aularioId, fecha, tipoMenu, platos) {
  const response = await fetch(
    `/entreaulas/api/comedor.php?action=save_menu&aulario=${aularioId}`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        date: fecha,
        menu_type: tipoMenu,
        plates: platos
      })
    }
  );
  return await response.json();
}
```

### Obtener tipos de menú

```javascript
async function obtenerTiposMenu(aularioId) {
  const response = await fetch(
    `/entreaulas/api/comedor.php?action=get_menu_types&aulario=${aularioId}`
  );
  const data = await response.json();
  return data.menu_types;
}
```

---

## Notas

- La API devuelve JSON con charset UTF-8
- Las fechas se usan en formato `YYYY-MM-DD`
- Los colores se especifican en formato hexadecimal (ej: `#0d6efd`)
- Las imágenes de pictogramas no se pueden subir directamente a través de la API JSON
- Para compartir datos de comedor entre aularios, usar la configuración de `shared_comedor_from` en el archivo del aulario
