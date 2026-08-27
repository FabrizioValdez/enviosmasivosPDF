# PROMPT MAESTRO — SISTEMA DE ACTUALIZACIÓN MASIVA DE PRODUCTOS MEDIANTE IA

## 1. CONTEXTO DEL PROYECTO

Estoy desarrollando un CRM/ERP para una empresa comercializadora de aceros, fierros y productos de acero inoxidable.

El proyecto actualmente utiliza:

* Laravel 8
* PHP 7.4
* PostgreSQL
* Vue.js para el frontend
* Laravel Queue para procesos en segundo plano
* Base de datos con más de 40,000 productos

La tabla de productos contiene información como:

* ID del producto
* Código del producto
* Código interno
* Descripción
* Familia
* Línea de venta
* Precio
* Stock
* Unidad de medida
* Medidas
* Marca
* Proveedor
* Otros campos comerciales y logísticos

La estructura exacta de la tabla deberá analizarse antes de implementar cualquier modificación.

---

# 2. OBJETIVO PRINCIPAL

Quiero implementar un sistema que permita subir archivos PDF provenientes de proveedores y utilizar IA para interpretar automáticamente la información de los productos contenidos en esos PDF.

Un PDF puede contener aproximadamente 500 productos.

Por ejemplo:

```text
Código | Descripción | Precio | Stock
A001   | Plancha Inox 304 2mm | 125.50 | 50
A002   | Plancha Inox 316 3mm | 180.00 | 20
A003   | Tubo Inox 304       | 95.00  | 35
...
```

El sistema debe:

1. Recibir el PDF.
2. Extraer la información.
3. Identificar los productos.
4. Encontrar los productos correspondientes en PostgreSQL.
5. Comparar la información actual contra la información del PDF.
6. Determinar qué campos deben actualizarse.
7. Validar los datos.
8. Actualizar masivamente la base de datos.
9. Registrar todo el proceso.
10. Informar errores o productos no encontrados.
11. Permitir revisión humana de casos dudosos.

---

# 3. REQUISITO FUNDAMENTAL DE ARQUITECTURA

NO quiero enviar los 40,000 productos de la base de datos a la IA.

Esto debe evitarse completamente.

La arquitectura debe ser:

```text
PDF
 ↓
Parser / extracción de texto o tablas
 ↓
Datos estructurados
 ↓
IA solamente cuando sea necesario
 ↓
JSON estructurado
 ↓
Backend Laravel
 ↓
Búsqueda en PostgreSQL
 ↓
Matching por código/SKU
 ↓
Validación
 ↓
Actualización masiva
 ↓
Producción
```

La IA NO debe encargarse de buscar dentro de los 40,000 productos.

PostgreSQL debe realizar el matching.

Ejemplo:

```sql
SELECT *
FROM products
WHERE product_code IN (...);
```

Debe existir un índice apropiado para las columnas utilizadas para identificar productos.

---

# 4. ESTRATEGIA DE REDUCCIÓN DE TOKENS

Quiero que el sistema esté diseñado para minimizar al máximo el consumo de tokens.

NO quiero:

```text
500 productos
↓
500 llamadas a IA
```

Debe buscarse algo como:

```text
PDF
↓
extracción
↓
1 o pocas llamadas a IA
↓
500 productos estructurados
```

Además, implementar una estrategia híbrida:

## Nivel 1 — Sin IA

Primero intentar interpretar el PDF mediante:

* extracción de texto
* extracción de tablas
* parser PDF
* expresiones regulares
* reglas determinísticas
* detección de columnas

Si el PDF tiene una estructura clara:

```text
PDF
↓
Parser
↓
Código
Precio
Stock
Descripción
↓
NO usar IA
```

## Nivel 2 — IA

Si el PDF no puede interpretarse correctamente:

```text
PDF
↓
Parser
↓
información ambigua
↓
IA
↓
JSON estructurado
```

## Nivel 3 — Revisión

Si existen datos ambiguos:

```text
confidence < threshold
↓
NO actualizar automáticamente
↓
revisión humana
```

La IA nunca debe actualizar directamente la base de datos.

---

# 5. MODELO DE IA

Evaluar y recomendar el modelo de IA más adecuado considerando:

* costo
* velocidad
* capacidad para interpretar tablas
* PDFs complejos
* extracción estructurada
* volumen de información
* límites de API
* latencia
* precisión
* escalabilidad

Como punto inicial evaluar con gemini-3.6-flash.

Sin embargo, NO asumir que necesariamente debe utilizarse un único modelo para todo.

Diseñar una estrategia donde:

* modelos económicos procesen casos sencillos
* modelos más potentes procesen casos complejos
* los casos dudosos pasen a revisión humana

La solución debe minimizar costos sin sacrificar precisión.

---

# 6. NO USAR IA PARA ACTUALIZAR DIRECTAMENTE LA BD

La IA solamente debe devolver datos estructurados.

Ejemplo:

```json
{
  "products": [
    {
      "supplier_code": "A001",
      "price": 125.50,
      "stock": 50,
      "currency": "USD"
    },
    {
      "supplier_code": "A002",
      "price": 180.00,
      "stock": 20,
      "currency": "USD"
    }
  ]
}
```

Laravel será responsable de:

* validar
* buscar
* comparar
* actualizar
* registrar
* controlar errores
* ejecutar transacciones

Nunca permitir que el modelo genere SQL directamente para producción.

---

# 7. IDENTIFICACIÓN DEL PRODUCTO

La prioridad para identificar productos debe ser:

1. Código exacto del producto
2. SKU
3. Código interno
4. Código del proveedor
5. Otros identificadores confiables

No utilizar embeddings como mecanismo principal si existe un código exacto.

Por ejemplo:

```text
PDF:
A001
```

Buscar:

```sql
SELECT *
FROM products
WHERE product_code = 'A001';
```

Si existe:

```text
MATCH = OK
```

Si no existe:

```text
MATCH = NOT_FOUND
```

No actualizar automáticamente productos que no puedan identificarse con suficiente confianza.

---

# 8. MATCHING INTELIGENTE

Diseñar un sistema de matching por niveles.

Ejemplo:

```text
Nivel 1
Código exacto
↓
100% confianza

Nivel 2
SKU exacto
↓
100% confianza

Nivel 3
Código proveedor
↓
alta confianza

Nivel 4
combinación de descripción + medidas + familia
↓
requiere validación

Nivel 5
IA / fuzzy matching
↓
solo para casos difíciles
```

Nunca modificar automáticamente un producto solamente porque su descripción sea parecida.

---

# 9. VALIDACIÓN

Antes de actualizar un producto:

```text
Producto encontrado
↓
validar código
↓
validar precio
↓
validar stock
↓
validar unidades
↓
validar campos
↓
comparar valores
↓
aprobar actualización
```

Ejemplos de validaciones:

### Precio

No aceptar:

```text
precio = -500
```

### Stock

No aceptar:

```text
stock = "ABC"
```

### Código

No aceptar código vacío.

### Datos sospechosos

Si:

```text
precio actual = 100
precio PDF = 10,000
```

marcar:

```text
WARNING
```

y enviar a revisión según las reglas configuradas.

---

# 10. SISTEMA DE IMPORTACIÓN

Crear una entidad de importación.

Tabla:

```text
product_imports
```

Propuesta:

```text
id
filename
status
total_products
processed_products
updated_products
failed_products
not_found_products
requires_review
created_at
started_at
completed_at
```

Estados posibles:

```text
PENDING
PROCESSING
VALIDATING
COMPLETED
COMPLETED_WITH_ERRORS
FAILED
REQUIRES_REVIEW
```

---

# 11. DETALLE DE IMPORTACIÓN

Crear:

```text
product_import_items
```

Propuesta:

```text
id
import_id
product_id
supplier_code
old_price
new_price
old_stock
new_stock
status
confidence
error_message
created_at
updated_at
```

Esto permitirá saber exactamente qué ocurrió con cada producto.

Ejemplo:

```text
IMPORTACIÓN #154

Total:              500
Actualizados:       472
No encontrados:      18
Con errores:         10
```

---

# 12. AUDITORÍA

Es obligatorio mantener un historial.

Por cada actualización guardar:

```text
producto
campo
valor anterior
valor nuevo
usuario/proceso
import_id
fecha
origen
```

Ejemplo:

```text
Producto: A001

Stock:
Anterior: 20
Nuevo: 50

Precio:
Anterior: 120
Nuevo: 125.50

Origen:
proveedor_agosto.pdf

Importación:
#154

Fecha:
2026-08-27
```

Esto permitirá saber quién, cuándo y por qué se modificó un producto.

---

# 13. ACTUALIZACIÓN MASIVA

NO hacer:

```text
UPDATE producto 1
UPDATE producto 2
UPDATE producto 3
...
UPDATE producto 500
```

si existe una alternativa eficiente.

Diseñar una estrategia de:

* bulk update
* upsert
* staging table
* temporary table
* PostgreSQL COPY
* operaciones masivas

según lo que sea más eficiente para PostgreSQL y Laravel.

Evaluar cuál es la mejor estrategia para 500, 5,000 y 50,000 registros.

---

# 14. TRANSACCIONES

La actualización debe ser segura.

Evaluar:

```text
BEGIN
↓
validaciones
↓
actualizaciones
↓
auditoría
↓
COMMIT
```

Si ocurre un error crítico:

```text
ROLLBACK
```

No dejar la base de datos en un estado parcialmente inconsistente.

También evaluar si conviene procesar por lotes/chunks para grandes volúmenes.

---

# 15. LARAVEL QUEUES

El procesamiento NO debe ejecutarse dentro de la petición HTTP.

Cuando el usuario suba:

```text
proveedor_agosto.pdf
```

hacer:

```text
Upload
↓
guardar archivo
↓
crear ProductImport
↓
dispatch Job
↓
respuesta inmediata al usuario
```

Luego:

```text
Queue
↓
Parse PDF
↓
Extract Data
↓
AI si es necesario
↓
Validate
↓
Match
↓
Bulk Update
↓
Audit
↓
Finish
```

Diseñar Jobs independientes, por ejemplo:

```text
ParseProductPdfJob
ExtractProductsJob
ValidateProductsJob
MatchProductsJob
UpdateProductsJob
```

Determinar si conviene mantenerlos separados o agrupar algunos según el rendimiento.

---

# 16. IDEMPOTENCIA

El sistema debe evitar actualizar dos veces accidentalmente el mismo PDF.

Si el usuario sube:

```text
proveedor_agosto.pdf
```

dos veces:

```text
Import #154
Import #155
```

el sistema debe poder detectar que el archivo ya fue procesado.

Evaluar:

* hash del archivo
* hash del contenido
* identificador del proveedor
* fecha
* versión del archivo

---

# 17. PREVISUALIZACIÓN ANTES DE PRODUCCIÓN

Quiero que el sistema pueda generar una vista previa antes de confirmar.

Ejemplo:

```text
┌──────────────────────────────────────────────┐
│ IMPORTACIÓN #154                             │
├──────────────────────────────────────────────┤
│ Total productos: 500                         │
│                                              │
│ Actualizar: 472                              │
│ No encontrados: 18                           │
│ Revisión manual: 10                          │
└──────────────────────────────────────────────┘
```

Detalle:

```text
Código | Precio anterior | Precio nuevo | Stock anterior | Stock nuevo | Estado
A001   | 120             | 125.50       | 20             | 50          | OK
A002   | 150             | 155          | 10             | 20          | OK
A003   | 200             | --            | 5              | --          | NO ENCONTRADO
```

El usuario debe poder:

```text
CONFIRMAR ACTUALIZACIÓN
```

o:

```text
CANCELAR
```

según el nivel de riesgo configurado.

---

# 18. REGLAS DE SEGURIDAD

La IA NO debe:

* ejecutar SQL
* eliminar productos
* modificar estructura de tablas
* crear usuarios
* modificar permisos
* acceder libremente a la base de datos
* actualizar productos sin validación

La IA únicamente debe entregar datos estructurados.

---

# 19. PROCESAMIENTO DE PDFs

Diseñar el sistema para diferentes tipos de PDF:

### PDF tipo tabla

```text
Código | Descripción | Precio | Stock
```

### PDF escaneado

Necesitar OCR.

### PDF con varias tablas

Detectar diferentes estructuras.

### PDF con columnas desordenadas

Usar IA como fallback.

### PDF con imágenes

Evaluar procesamiento visual cuando sea necesario.

El sistema debe intentar primero la solución más barata y determinística.

---

# 20. NORMALIZACIÓN DE DATOS

El sistema debe poder normalizar:

```text
$ 1,250.50
USD 1250.50
1.250,50
1250,50
```

y convertirlo correctamente según las reglas del proveedor.

También:

```text
50 UND
50 UN
50 unidades
50
```

debe poder convertirse al formato interno correspondiente.

Definir reglas por proveedor cuando sea necesario.

---

# 21. CONFIGURACIÓN POR PROVEEDOR

Diseñar pensando que en el futuro existirán muchos proveedores.

Por ejemplo:

```text
Proveedor A
→ código columna 1
→ precio columna 4
→ stock columna 5

Proveedor B
→ código columna 2
→ precio columna 6
→ stock columna 7
```

No quiero programar nuevamente todo el sistema para cada proveedor.

Crear configuraciones/adapters/parsers por proveedor cuando sea necesario.

---

# 22. ARQUITECTURA DEL SISTEMA

Proponer una arquitectura limpia para Laravel.

Evaluar componentes como:

```text
app/
├── Domain/
│   └── Products/
│
├── Services/
│   └── ProductImport/
│
├── Jobs/
│
├── Models/
│
├── Repositories/
│
├── AI/
│
└── Parsers/
```

No asumir esta estructura como obligatoria.

Analizar primero la arquitectura actual del proyecto y adaptar la solución a ella.

---

# 23. FRONTEND VUE

Crear una interfaz para:

### Subir PDF

```text
[ Seleccionar PDF ]

[ Procesar ]
```

### Estado

```text
Procesando PDF...

████████████████░░░░ 80%

Productos procesados:
400 / 500
```

### Resultado

```text
500 productos encontrados

472 actualizados
18 no encontrados
10 requieren revisión
```

### Revisión

Mostrar únicamente los casos problemáticos.

---

# 24. RENDIMIENTO

La solución debe estar diseñada para:

```text
40,000 productos actuales
+
500 productos por PDF
+
posibilidad de crecer a 100,000+
```

Optimizar:

* índices PostgreSQL
* consultas
* memoria PHP
* queues
* chunks
* bulk operations
* procesamiento de PDF
* llamadas a IA
* concurrencia
* cache cuando sea útil

NO cargar los 40,000 productos en memoria.

NO enviar los 40,000 productos a la IA.

NO hacer 500 llamadas individuales a la IA si puede resolverse en una o pocas llamadas.

---

# 25. ESCALABILIDAD

Diseñar para que posteriormente pueda procesar:

```text
500 productos
1,000 productos
5,000 productos
10,000 productos
50,000 productos
```

sin tener que rehacer la arquitectura.

Evaluar cuándo conviene:

```text
Laravel Queue
Redis
Horizon
PostgreSQL staging tables
Batch processing
OpenAI Batch API
```

No introducir tecnologías innecesarias si no aportan valor.

---

# 26. MANEJO DE ERRORES

Cada error debe quedar registrado.

Ejemplos:

```text
PDF inválido
Producto sin código
Precio inválido
Stock inválido
Producto no encontrado
Código duplicado
Formato desconocido
Error de IA
Error de PostgreSQL
Timeout
Error de OCR
```

El procesamiento debe poder continuar con los productos válidos cuando sea seguro hacerlo.

---

# 27. OBSERVABILIDAD

Registrar:

```text
tiempo de procesamiento
cantidad de productos
tokens utilizados
costo estimado de IA
cantidad de llamadas a IA
errores
productos actualizados
productos rechazados
```

Quiero poder medir cuánto cuesta procesar cada PDF.

Ejemplo:

```text
PDF: proveedor_agosto.pdf

Productos: 500
IA calls: 2
Input tokens: 85,000
Output tokens: 12,000
Costo IA: $X
Tiempo total: 18 segundos

Actualizados: 472
Revisión: 10
No encontrados: 18
```

---

# 28. OBJETIVO DE COSTOS

Optimizar para que la IA sea utilizada solamente cuando realmente sea necesaria.

Prioridad:

```text
1. Reglas determinísticas
2. Parser PDF
3. PostgreSQL
4. IA económica
5. IA potente solamente cuando sea necesario
6. Revisión humana
```

No utilizar IA para tareas que PostgreSQL, PHP o un parser puedan realizar mejor y más barato.

---

# 29. TECNOLOGÍAS EXISTENTES

El proyecto actual utiliza aproximadamente:

```text
Laravel 8
PHP 7.4
PostgreSQL
Vue.js
Laravel Queue
```

Antes de proponer código:

1. Analizar la versión real de Laravel.
2. Analizar PHP.
3. Analizar PostgreSQL.
4. Analizar estructura actual del proyecto.
5. Analizar tabla real de productos.
6. Analizar índices actuales.
7. Analizar cómo se manejan actualmente precios y stock.
8. Analizar cómo se conectan las demás áreas del CRM.

No inventar nombres de tablas ni columnas.

---

# 30. IMPORTANTE: NO EMPEZAR PROGRAMANDO INMEDIATAMENTE

Primero quiero que analices la arquitectura completa.

Quiero que respondas en este orden:

## Fase 1 — Análisis

Explicar:

* arquitectura recomendada
* flujo completo
* modelo de IA recomendado
* estrategia de tokens
* estrategia de procesamiento PDF
* estrategia de matching
* estrategia de actualización masiva
* estrategia de seguridad
* estrategia de auditoría
* estrategia de colas
* estrategia de escalabilidad

## Fase 2 — Diseño

Proponer:

* tablas
* relaciones
* índices
* Jobs
* Services
* interfaces
* parsers
* flujo frontend
* flujo backend
* estructura de carpetas

## Fase 3 — Integración IA

Definir:

* modelo
* API
* prompt de extracción
* JSON Schema
* validaciones
* manejo de errores
* fallback
* control de costos

## Fase 4 — Implementación

Después de validar el diseño, comenzar a implementar:

1. migraciones
2. modelos
3. services
4. Jobs
5. parser PDF
6. integración IA
7. validaciones
8. matching
9. actualización masiva
10. auditoría
11. API
12. Vue
13. pruebas

---

# 31. REGLA PRINCIPAL

La solución final debe seguir esta filosofía:

```text
                    PDF
                     │
                     ▼
             ┌───────────────┐
             │ PDF PARSER    │
             └───────┬───────┘
                     │
              ¿Se entiende?
                /          \
              SÍ            NO
              │              │
              │              ▼
              │        ┌───────────┐
              │        │    IA     │
              │        └─────┬─────┘
              │              │
              └──────┬───────┘
                     ▼
              JSON ESTRUCTURADO
                     │
                     ▼
             VALIDACIÓN BACKEND
                     │
                     ▼
             MATCH EN POSTGRESQL
                     │
              ┌──────┴──────┐
              ▼             ▼
            OK           DUDOSO
              │             │
              ▼             ▼
       BULK UPDATE      REVISIÓN
              │
              ▼
          AUDITORÍA
              │
              ▼
       BASE DE PRODUCCIÓN
```

## PRINCIPIO FUNDAMENTAL

**La IA interpreta.
Laravel valida.
PostgreSQL busca.
Laravel actualiza.
El sistema audita.**

Nunca hacer:

```text
40,000 productos → IA
```

Nunca hacer:

```text
IA → SQL → producción
```

La solución debe ser eficiente, segura, económica, escalable y preparada para manejar decenas de miles de productos.

---

# 32. LO QUE ESPERO DE TU RESPUESTA

Quiero una respuesta técnica y práctica.

No quiero solamente una explicación conceptual.

Primero presenta:

### A. Arquitectura general

### B. Diagrama del flujo

### C. Tecnologías recomendadas

### D. Modelo de IA recomendado y alternativas

### E. Estrategia para minimizar tokens

### F. Diseño de base de datos

### G. Diseño de Laravel

### H. Diseño de Queue

### I. Diseño de procesamiento PDF

### J. Diseño de matching

### K. Diseño de actualización masiva

### L. Diseño de auditoría

### M. Diseño de Vue

### N. Seguridad

### O. Rendimiento

### P. Estimación de costos

### Q. Plan de implementación por fases

Después de eso, espera a que te proporcione la estructura real de mi base de datos y el código actual del proyecto antes de escribir modificaciones concretas.

**No inventes columnas, tablas, modelos ni relaciones que no existan.**
