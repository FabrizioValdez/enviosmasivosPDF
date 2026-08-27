<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Actualización Masiva de Productos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        [v-cloak] { display: none; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    @verbatim
    <div id="app" v-cloak>
        <nav class="bg-blue-600 text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 py-4">
                <h1 class="text-xl font-bold">Sistema de Actualizacion Masiva de Productos</h1>
            </div>
        </nav>

        <div class="max-w-7xl mx-auto px-4 py-8">
            <!-- Tabs -->
            <div class="mb-6 border-b border-gray-200">
                <nav class="flex space-x-8">
                    <button
                        @click="activeTab = 'imports'"
                        :class="activeTab === 'imports' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-2 px-1 border-b-2 font-medium text-sm"
                    >
                        Importaciones
                    </button>
                    <button
                        @click="activeTab = 'products'"
                        :class="activeTab === 'products' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-2 px-1 border-b-2 font-medium text-sm"
                    >
                        Productos
                    </button>
                    <button
                        @click="activeTab = 'suppliers'"
                        :class="activeTab === 'suppliers' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-2 px-1 border-b-2 font-medium text-sm"
                    >
                        Proveedores
                    </button>
                </nav>
            </div>

            <!-- Imports Tab -->
            <div v-if="activeTab === 'imports'">
                <!-- Upload Form -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-4">Nueva Importacion</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Proveedor</label>
                            <select v-model="uploadForm.supplier_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Seleccionar proveedor</option>
                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                    {{ supplier.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Archivo PDF</label>
                            <input
                                type="file"
                                ref="fileInput"
                                @change="handleFileSelect"
                                accept=".pdf"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                        </div>
                        <div class="flex items-end">
                            <button
                                @click="uploadFile"
                                :disabled="!uploadForm.supplier_id || !uploadForm.file || uploading"
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                            >
                                <span v-if="uploading">Procesando...</span>
                                <span v-else>Subir y Procesar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    <div v-if="uploading" class="mt-4">
                        <div class="bg-blue-100 border border-blue-300 rounded-md p-4">
                            <div class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-blue-800">Procesando archivo PDF...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Imports List -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold">Historial de Importaciones</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archivo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actualizados</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No Encontrados</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="import_ in imports" :key="import_.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ import_.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ import_.filename }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ getSupplierName(import_.supplier_id) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusClass(import_.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                        {{ import_.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ import_.total_products }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">{{ import_.updated_products }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">{{ import_.not_found_products }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button @click="showImportDetail(import_)" class="text-blue-600 hover:text-blue-900 mr-2">Ver</button>
                                    <button
                                        v-if="import_.status === 'REQUIRES_REVIEW'"
                                        @click="confirmImport(import_)"
                                        class="text-green-600 hover:text-green-900 mr-2"
                                    >Confirmar</button>
                                    <button
                                        v-if="import_.status !== 'COMPLETED' && import_.status !== 'FAILED'"
                                        @click="cancelImport(import_)"
                                        class="text-red-600 hover:text-red-900"
                                    >Cancelar</button>
                                </td>
                            </tr>
                            <tr v-if="imports.length === 0">
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">No hay importaciones registradas</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Products Tab -->
            <div v-if="activeTab === 'products'">
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">Productos</h2>
                        <input
                            v-model="productSearch"
                            @input="searchProducts"
                            type="text"
                            placeholder="Buscar producto..."
                            class="border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Codigo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripcion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Familia</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="product in products" :key="product.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ product.product_code }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ product.description }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ product.family }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ product.price }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ product.stock }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button @click="showProductAuditLog(product)" class="text-blue-600 hover:text-blue-900">Historial</button>
                                </td>
                            </tr>
                            <tr v-if="products.length === 0">
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No se encontraron productos</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Suppliers Tab -->
            <div v-if="activeTab === 'suppliers'">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">Proveedores</h2>
                        <button @click="showAddSupplier = true" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            Nuevo Proveedor
                        </button>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Codigo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notas</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="supplier in suppliers" :key="supplier.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ supplier.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ supplier.code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="supplier.active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                        {{ supplier.active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ supplier.notes }}</td>
                            </tr>
                            <tr v-if="suppliers.length === 0">
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No hay proveedores registrados</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Import Detail Modal -->
            <div v-if="selectedImport" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Detalle de Importacion #{{ selectedImport.id }}</h3>
                        <button @click="selectedImport = null" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ selectedImport.total_products }}</div>
                            <div class="text-sm text-gray-600">Total</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ selectedImport.updated_products }}</div>
                            <div class="text-sm text-gray-600">Actualizados</div>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-red-600">{{ selectedImport.not_found_products }}</div>
                            <div class="text-sm text-gray-600">No Encontrados</div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">{{ selectedImport.requires_review }}</div>
                            <div class="text-sm text-gray-600">Revision</div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Codigo</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Confianza</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Precio Ant.</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Precio Nuevo</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stock Ant.</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stock Nuevo</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in importItems" :key="item.id">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ item.supplier_code }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <span :class="getItemStatusClass(item.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ item.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ formatConfidence(item.confidence) }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">${{ item.old_price }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">${{ item.new_price }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ item.old_stock }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ item.new_stock }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Product Audit Log Modal -->
            <div v-if="selectedProduct" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Historial de Cambios - {{ selectedProduct.product_code }}</h3>
                        <button @click="selectedProduct = null" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Campo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valor Anterior</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valor Nuevo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Origen</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="log in productAuditLogs" :key="log.id">
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ formatDate(log.created_at) }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ log.field }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ log.old_value }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ log.new_value }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ log.source }}</td>
                            </tr>
                            <tr v-if="productAuditLogs.length === 0">
                                <td colspan="5" class="px-4 py-2 text-center text-sm text-gray-500">No hay cambios registrados</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Supplier Modal -->
            <div v-if="showAddSupplier" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Nuevo Proveedor</h3>
                        <button @click="showAddSupplier = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre *</label>
                            <input v-model="newSupplier.name" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Codigo</label>
                            <input v-model="newSupplier.code" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notas</label>
                            <textarea v-model="newSupplier.notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button @click="showAddSupplier = false" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">Cancelar</button>
                            <button @click="createSupplier" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Guardar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <div v-if="notification" class="fixed bottom-4 right-4 z-50">
                <div :class="notification.type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="text-white px-6 py-3 rounded-lg shadow-lg">
                    {{ notification.message }}
                </div>
            </div>
        </div>
    </div>
    @endverbatim

    <script>
        const { createApp, ref, onMounted, watch } = Vue;

        createApp({
            setup() {
                const activeTab = Vue.ref('imports');
                const imports = Vue.ref([]);
                const products = Vue.ref([]);
                const suppliers = Vue.ref([]);
                const importItems = Vue.ref([]);
                const productAuditLogs = Vue.ref([]);

                const uploadForm = Vue.ref({
                    supplier_id: '',
                    file: null
                });
                const uploading = Vue.ref(false);

                const productSearch = Vue.ref('');
                const selectedImport = Vue.ref(null);
                const selectedProduct = Vue.ref(null);
                const showAddSupplier = Vue.ref(false);

                const newSupplier = Vue.ref({
                    name: '',
                    code: '',
                    notes: ''
                });

                const notification = Vue.ref(null);

                const api = axios.create({
                    baseURL: '/api/v1',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const showNotification = (message, type) => {
                    type = type || 'success';
                    notification.value = { message: message, type: type };
                    setTimeout(() => { notification.value = null; }, 3000);
                };

                const getSupplierName = (supplierId) => {
                    const supplier = suppliers.value.find(s => s.id === supplierId);
                    return supplier ? supplier.name : 'N/A';
                };

                const loadImports = async () => {
                    try {
                        const response = await api.get('/imports');
                        imports.value = response.data.data;
                    } catch (error) {
                        console.error('Error loading imports:', error);
                    }
                };

                const loadProducts = async () => {
                    try {
                        const params = productSearch.value ? { search: productSearch.value } : {};
                        const response = await api.get('/products', { params: params });
                        products.value = response.data.data;
                    } catch (error) {
                        console.error('Error loading products:', error);
                    }
                };

                const loadSuppliers = async () => {
                    try {
                        const response = await api.get('/suppliers');
                        suppliers.value = response.data;
                    } catch (error) {
                        console.error('Error loading suppliers:', error);
                    }
                };

                const handleFileSelect = (event) => {
                    uploadForm.value.file = event.target.files[0];
                };

                const uploadFile = async () => {
                    if (!uploadForm.value.supplier_id || !uploadForm.value.file) return;

                    uploading.value = true;
                    const formData = new FormData();
                    formData.append('file', uploadForm.value.file);
                    formData.append('supplier_id', uploadForm.value.supplier_id);

                    try {
                        await api.post('/imports', formData, {
                            headers: { 'Content-Type': 'multipart/form-data' }
                        });
                        showNotification('Importacion iniciada correctamente');
                        uploadForm.value = { supplier_id: '', file: null };
                        document.querySelector('input[type="file"]').value = '';
                        loadImports();
                    } catch (error) {
                        showNotification(error.response?.data?.message || 'Error al subir archivo', 'error');
                    } finally {
                        uploading.value = false;
                    }
                };

                const showImportDetail = async (import_) => {
                    selectedImport.value = import_;
                    try {
                        const response = await api.get('/imports/' + import_.id + '/items');
                        importItems.value = response.data.data;
                    } catch (error) {
                        console.error('Error loading import items:', error);
                    }
                };

                const confirmImport = async (import_) => {
                    try {
                        await api.post('/imports/' + import_.id + '/confirm');
                        showNotification('Importacion confirmada');
                        loadImports();
                        selectedImport.value = null;
                    } catch (error) {
                        showNotification('Error al confirmar importacion', 'error');
                    }
                };

                const cancelImport = async (import_) => {
                    if (!confirm('Esta seguro de cancelar esta importacion?')) return;
                    try {
                        await api.post('/imports/' + import_.id + '/cancel');
                        showNotification('Importacion cancelada');
                        loadImports();
                    } catch (error) {
                        showNotification('Error al cancelar importacion', 'error');
                    }
                };

                const searchProducts = () => {
                    loadProducts();
                };

                const showProductAuditLog = async (product) => {
                    selectedProduct.value = product;
                    try {
                        const response = await api.get('/products/' + product.id + '/audit-log');
                        productAuditLogs.value = response.data.data;
                    } catch (error) {
                        console.error('Error loading audit log:', error);
                    }
                };

                const createSupplier = async () => {
                    try {
                        await api.post('/suppliers', newSupplier.value);
                        showNotification('Proveedor creado correctamente');
                        showAddSupplier.value = false;
                        newSupplier.value = { name: '', code: '', notes: '' };
                        loadSuppliers();
                    } catch (error) {
                        showNotification('Error al crear proveedor', 'error');
                    }
                };

                const getStatusClass = (status) => {
                    const classes = {
                        'PENDING': 'bg-gray-100 text-gray-800',
                        'PROCESSING': 'bg-blue-100 text-blue-800',
                        'VALIDATING': 'bg-yellow-100 text-yellow-800',
                        'COMPLETED': 'bg-green-100 text-green-800',
                        'COMPLETED_WITH_ERRORS': 'bg-orange-100 text-orange-800',
                        'FAILED': 'bg-red-100 text-red-800',
                        'REQUIRES_REVIEW': 'bg-purple-100 text-purple-800'
                    };
                    return classes[status] || 'bg-gray-100 text-gray-800';
                };

                const getItemStatusClass = (status) => {
                    const classes = {
                        'PENDING': 'bg-gray-100 text-gray-800',
                        'MATCHED': 'bg-blue-100 text-blue-800',
                        'UPDATED': 'bg-green-100 text-green-800',
                        'NOT_FOUND': 'bg-red-100 text-red-800',
                        'FAILED': 'bg-red-100 text-red-800',
                        'REQUIRES_REVIEW': 'bg-yellow-100 text-yellow-800'
                    };
                    return classes[status] || 'bg-gray-100 text-gray-800';
                };

                const formatDate = (date) => {
                    return new Date(date).toLocaleString('es-ES');
                };

                const formatConfidence = (confidence) => {
                    return (confidence * 100).toFixed(1) + '%';
                };

                Vue.onMounted(() => {
                    loadImports();
                    loadProducts();
                    loadSuppliers();
                });

                Vue.watch(activeTab, (tab) => {
                    if (tab === 'imports') loadImports();
                    if (tab === 'products') loadProducts();
                    if (tab === 'suppliers') loadSuppliers();
                });

                return {
                    activeTab: activeTab,
                    imports: imports,
                    products: products,
                    suppliers: suppliers,
                    importItems: importItems,
                    productAuditLogs: productAuditLogs,
                    uploadForm: uploadForm,
                    uploading: uploading,
                    productSearch: productSearch,
                    selectedImport: selectedImport,
                    selectedProduct: selectedProduct,
                    showAddSupplier: showAddSupplier,
                    newSupplier: newSupplier,
                    notification: notification,
                    handleFileSelect: handleFileSelect,
                    uploadFile: uploadFile,
                    showImportDetail: showImportDetail,
                    confirmImport: confirmImport,
                    cancelImport: cancelImport,
                    searchProducts: searchProducts,
                    showProductAuditLog: showProductAuditLog,
                    createSupplier: createSupplier,
                    getStatusClass: getStatusClass,
                    getItemStatusClass: getItemStatusClass,
                    formatDate: formatDate,
                    formatConfidence: formatConfidence,
                    getSupplierName: getSupplierName
                };
            }
        }).mount('#app');
    </script>
</body>
</html>
