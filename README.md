# Sistema de Actualización Masiva de Productos

## Resumen de Mejoras

Migración del servicio de IA de **Anthropic (Claude)** a **Google Gemini**, con un motor de matching en PHP que reemplaza la dependencia de cargar el catálogo completo en el prompt de la IA.

---

## Comparación: Sistema Anterior vs Nuevo

| Aspecto | Sistema Anterior (Anthropic) | Nuevo Sistema (Gemini) |
|---------|-------------------------------|------------------------|
| **Proveedor IA** | Claude Haiku 4.5 | Gemini 3.6 Flash |
| **API** | `api.anthropic.com/v1/messages` | `generativelanguage.googleapis.com/v1beta` |
| **Costo por 1M tokens** | ~$1.00 input / $5.00 output | ~$0.075 input / $0.30 output |
| **Matching** | IA carga catálogo en prompt y decide | IA extrae datos, PHP compara contra BD |
| **Límite de productos** | ~500 por prompt (contexto limitado) | Sin límite (matching en BD) |
| **Velocidad** | Depende de la respuesta de la IA | Matching local instantáneo |
| **Reintentos** | No tenía | Reintentos con backoff exponencial (429/503) |
| **Análisis de PDF** | Solo texto extraído | Envío directo del PDF a Gemini Vision |
| **Confianza del matching** | No existía | Score de 0.0 a 1.0 con umbrales configurables |
| **Audit trail** | No existía | Log de cada cambio con valores anterior/nuevo |

---

## Bugs Corregidos

### 1. Nombre de modelo inválido
- **Archivo:** `.env`, `config/services.php`
- **Problema:** El modelo `gemini-3.6-flash` no existía en la API v1beta de Gemini
- **Solución:** Se verificó el nombre correcto del modelo y se actualizó la configuración

### 2. JSON con code fences markdown
- **Archivo:** `app/Services/Ai/GeminiProvider.php`
- **Problema:** Gemini devolvía JSON envuelto en `` ```json\n{...}\n``` ``, causando fallo en `json_decode`
- **Solución:** Nuevo método `stripCodeFences()` que elimina los markers antes de decodificar

### 3. Columnas faltantes en migración
- **Archivo:** `database/migrations/2024_01_01_000004_create_product_import_items_table.php`
- **Problema:** Las columnas `old_stock` y `new_stock` usaban `->change()` dentro de un `Schema::create()`, lo cual falla
- **Solución:** Nueva migración `2026_08_27_163233_add_old_stock_new_stock_to_product_import_items_table.php`

### 4. Matching incorrecto de productos
- **Archivo:** `app/Services/ProductImport/ProductMatchingService.php`
- **Problema:** "BOBINA" matcheaba contra "BARRA", y "3/8" matcheaba contra "12MM"
- **Solución:** Nuevos métodos `extractProductType()` y `extractDiameter()` filtran por tipo y diámetro antes del scoring

### 5. Normalización de keywords deficiente
- **Archivo:** `app/Services/ProductImport/ProductMatchingService.php`
- **Problema:** "GR60" (una palabra) no matcheaba contra "GR 60" (dos palabras). "A615/A706" no se separaba. "CONSTRUCCION" no se normalizaba a "CORRUGAD"
- **Solución:** Nuevo método `normalizeText()` + reglas en `normalizeKeyword()`

### 6. Sin manejo de errores transitorios
- **Archivo:** `app/Services/Ai/GeminiProvider.php`
- **Problema:** Errores 429 (rate limit) y 503 (sobrecarga) causaban fallo inmediato
- **Solución:** Reintentos con backoff exponencial: 3s → 6s → 12s (máximo 3 intentos)

---

## Arquitectura del Nuevo Sistema

```
┌─────────────────────────────────────────────────────────┐
│                    Flujo de Importación                  │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  PDF Upload                                             │
│      │                                                  │
│      ▼                                                  │
│  ┌──────────────────┐                                   │
│  │ PdfParserFactory │ ← Extrae texto del PDF            │
│  └────────┬─────────┘                                   │
│           │                                             │
│           ▼                                             │
│  ┌──────────────────┐     ┌───────────────────┐        │
│  │   AiService      │────▶│  GeminiProvider   │        │
│  │  (extractProducts)│     │  - stripCodeFences│        │
│  └────────┬─────────┘     │  - Reintentos     │        │
│           │               │  - Cost tracking  │        │
│           ▼               └───────────────────┘        │
│  ┌──────────────────┐                                   │
│  │ Products[]       │ ← [{code, description, stock}]   │
│  └────────┬─────────┘                                   │
│           │                                             │
│           ▼                                             │
│  ┌──────────────────────────────┐                       │
│  │  ProductMatchingService      │                       │
│  │  1. matchByCode (exacto)     │ ← confidence: 1.0    │
│  │  2. matchBySku (exacto)      │ ← confidence: 1.0    │
│  │  3. matchBySupplierCode      │ ← confidence: 0.95   │
│  │  4. matchBySmartDescription  │ ← type + diameter +  │
│  │  5. matchByKeywords          │   keyword scoring    │
│  └────────┬─────────────────────┘                       │
│           │                                             │
│           ▼                                             │
│  ┌──────────────────┐                                   │
│  │ ProductUpdateService │ ← Solo actualiza si hay match │
│  │  - detectChanges     │                               │
│  │  - bulkUpdate        │                               │
│  │  - logAudit          │                               │
│  └──────────────────┘                                   │
│                                                         │
│  REGLA: Si no hay match → NO se crea ni se actualiza    │
└─────────────────────────────────────────────────────────┘
```

---

## Motor de Matching Detallado

### Nivel 1: Coincidencia Exacta (confidence: 1.0)

| Método | Campo BD | Campo PDF | Ejemplo |
|--------|----------|-----------|---------|
| `matchByCode` | `product_code` | `code` | "9552" → "9552" |
| `matchBySku` | `sku` | `code` | "A005" → "A005" |

### Nivel 2: Código de Proveedor (confidence: 0.95)

| Método | Campo BD | Campo PDF |
|--------|----------|-----------|
| `matchBySupplierCode` | `supplier_code` | `code` | "SUP-001" → "SUP-001" |

### Nivel 3: Descripción Inteligente (confidence: variable)

Antes de comparar keywords, el sistema verifica:

1. **Tipo de producto** (`extractProductType`):
   - Extrae la primera palabra: barra, bobina, tubo, plancha, perfil, etc.
   - Si el tipo no coincide → descarta inmediatamente
   - Ejemplo: "BOBINA ACERO..." ≠ "BARRA ACERO..." → **NO matchea**

2. **Diámetro** (`extractDiameter`):
   - Maneja fracciones: 3/8", 1/2", 5/8", 3/4", 1"
   - Maneja métrico: 6MM, 8MM, 12MM
   - Convierte todo a milímetros para comparar
   - Tolerancia: ±1mm
   - Ejemplo: "3/8" (9.525mm) ≠ "12MM" → **NO matchea**

3. **Scoring de keywords** (`calculateKeywordScore`):
   - Extrae y normaliza keywords de ambas descripciones
   - Compara con pesos por posición (primera palabra = peso 10)
   - Score = peso_matched / peso_total

### Normalización de Texto (`normalizeText`)

```
"GR60"          → "GR 60"         (separa grado del número)
"A615/A706"     → "A615 A706"     (separa grados ASTM)
"CONSTRUCCION"  → "CORRUGAD"      (sinónimo en sector acerero)
"PULGADA"       → "IN"            (unidad equivalente)
"ACERO" / "FIERRO" → "ACERO"     (sinónimo)
```

### Ejemplo Real

```
PDF:  "BARRA DE CONSTRUCCION ASTM A615/A706 GR60 3/8 X 9M"
DB:   "BARRA CORRUGADA ACERO ASTM A706 GR 60 3/8 PULGADA"

Tipo:     barra == barra ✓
Diámetro: 3/8 (9.525mm) == 3/8 (9.525mm) ✓
Keywords: 8/10 coinciden
Score:    0.82 > umbral 0.7 → MATCH ✓
```

---

## Configuración de Producción

### Variables de Entorno

```env
# Gemini API
GEMINI_API_KEY=tu_api_key_aqui
GEMINI_MODEL=gemini-3.6-flash
AI_DEFAULT_PROVIDER=gemini

# Matching
PRODUCT_IMPORT_CONFIDENCE_THRESHOLD=0.7
PRODUCT_IMPORT_MAX_PRICE_CHANGE=500
```

### Configuración del Modelo

| Parámetro | Valor | Descripción |
|-----------|-------|-------------|
| `temperature` | 0.1 | Respuestas deterministas |
| `maxOutputTokens` | 8192 | Suficiente para ~100 productos |
| `responseMimeType` | `application/json` | Fuerza respuesta JSON |

---

## Migraciones de BD

### Columnas Faltantes

```php
// database/migrations/2026_08_27_163233_add_old_stock_new_stock_to_product_import_items_table.php
Schema::table('product_import_items', function (Blueprint $table) {
    $table->decimal('old_stock', 15, 2)->nullable()->after('new_price');
    $table->decimal('new_stock', 15, 2)->nullable()->after('old_stock');
});
```

### Índices Recomendados para Producción

```sql
-- Para matching rápido por código
CREATE INDEX idx_products_product_code ON products(product_code);
CREATE INDEX idx_products_sku ON products(sku);
CREATE INDEX idx_products_supplier_code ON products(supplier_code);

-- Para búsqueda por descripción
CREATE INDEX idx_products_active ON products(active);
CREATE INDEX idx_products_description_gin ON products USING gin(to_tsvector('spanish', description));

-- Para tracking de imports
CREATE INDEX idx_product_import_items_import_id ON product_import_items(import_id);
CREATE INDEX idx_product_import_items_status ON product_import_items(status);
```

---

## Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `.env` | Modelo corregido a `gemini-3.6-flash` |
| `config/services.php` | Default del modelo corregido |
| `app/Services/Ai/GeminiProvider.php` | stripCodeFences, reintentos, logging mejorado |
| `app/Services/Ai/AiProviderInterface.php` | Agregados `getTotalInputTokens()`, `getTotalOutputTokens()` |
| `app/Services/Ai/AiService.php` | Fix branch muerto (ambos creaban GeminiProvider) |
| `app/Services/ProductImport/ProductMatchingService.php` | extractProductType, extractDiameter, normalizeText, normalizeKeyword |
| `app/Services/ProductImport/ProductImportOrchestrator.php` | Envío de PDF directo a Gemini Vision |
| `database/migrations/2026_08_27_163233_*` | Columnas old_stock, new_stock |

---

## Deployment Checklist

- [ ] Copiar `.env` con `GEMINI_API_KEY` de producción
- [ ] Ejecutar migración: `php artisan migrate`
- [ ] Limpiar caché: `php artisan config:clear && php artisan cache:clear`
- [ ] Verificar modelo: `php artisan tinker --execute="echo config('services.gemini.model');"` → debe mostrar `gemini-3.6-flash`
- [ ] Test con PDF pequeño (10 productos) → verificar logs
- [ ] Test con PDF completo (100 productos) → verificar matching
- [ ] Monitorear primeros 10 imports en producción
- [ ] Verificar que `product_import_items` tiene columnas `old_stock` y `new_stock`

---

## Costo Estimado por Import

| Concepto | Cálculo | Costo |
|----------|---------|-------|
| Input tokens | ~500 productos × 50 tokens = 25K | ~$0.002 |
| Output tokens | ~500 productos × 30 tokens = 15K | ~$0.005 |
| **Total por import** | | **~$0.007** |

Para 10 imports diarios: **~$0.07/día** o **~$2.10/mes**.

---

## Riesgos Conocidos

1. **Rate limits de Gemini:** Mitigado con reintentos (3 intentos, backoff exponencial)
2. **Archivos grandes (>100K caracteres):** Mitigado con chunking automático de 80K
3. **Matching ambiguo (2 productos con score similar):** Selecciona el de mayor score. Umbral configurable.
4. **PDF escaneado (imagen):** Gemini Vision lo procesa directamente sin necesidad de OCR previo
5. **API key expuesta en logs:** El log incluye status y body pero NO la URL completa con key

---

## Próximos Pasos (Opcional)

1. **Laravel Scout + Meilisearch:** Para catálogos >50K productos, indexar descripciones para búsqueda full-text
2. **Streaming de respuestas:** Para PDFs muy grandes, procesar por páginas
3. **UI de revisión:** Mostrar matches con score <0.8 para revisión manual antes de actualizar
4. **Reporte de import:** Generar PDF/Excel con resumen de cambios aplicados
