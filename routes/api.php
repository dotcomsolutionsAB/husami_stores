<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserAccessController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\UploadsController;
use App\Http\Controllers\GodownController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\CounterController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ProductStockController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\SuppliersController;
use App\Http\Controllers\PickUpCartController;
use App\Http\Controllers\PickUpSlipController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\ProformaController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\BookOrderController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseBagController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\GrnController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\MasterDataController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/register', [UserController::class, 'create']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum', 'role:admin,user')->group(function () {

    Route::get('/dashboard', [UserController::class, 'summary']);
    // users route
    Route::prefix('users')->group(function () {
        Route::post('/create', [UserController::class, 'create']);
        Route::post('/retrieve/{id?}', [UserController::class, 'fetch']);
        Route::post('/update/{id}', [UserController::class, 'edit']);
        Route::delete('/delete/{id}', [UserController::class, 'delete']);
        // Route::post('/reset_password', [AuthController::class, 'updatePassword']);
        Route::post('/export', [UserController::class, 'exportExcel']);
        Route::post('/change_password', [UserController::class, 'updatePassword']);
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    // user-access route
    Route::prefix('user_access')->group(function () {
            Route::post('/create', [UserAccessController::class, 'create']);
            Route::post('/retrieve/{id?}', [UserAccessController::class, 'fetch']);
            Route::post('/update/{id}', [UserAccessController::class, 'edit']);
            Route::delete('/delete/{id}', [UserAccessController::class, 'delete']);
    });

    // brand route
    Route::prefix('brand')->group(function () {
        Route::post('/create', [BrandController::class, 'create']);
        Route::post('/retrieve/{id?}', [BrandController::class, 'fetch']);
        Route::post('/update/{id}', [BrandController::class, 'edit']);
        Route::delete('/delete/{id}', [BrandController::class, 'delete']);
    });

    // uploads route
    Route::prefix('upload')->group(function () {
        Route::post('/create', [UploadsController::class, 'create']);
        Route::post('/retrieve/{id?}', [UploadsController::class, 'fetch']);
        Route::post('/update/{id}', [UploadsController::class, 'edit']);
        Route::delete('/delete/{id}', [UploadsController::class, 'delete']);
    });

    // godown route
    Route::prefix('godown')->group(function () {
        Route::post('/create', [GodownController::class, 'create']);
        Route::post('/retrieve/{id?}', [GodownController::class, 'fetch']);
        Route::post('/update/{id}', [GodownController::class, 'edit']);
        Route::delete('/delete/{id}', [GodownController::class, 'delete']);
        Route::post('/export', [GodownController::class, 'exportExcel']);
    });

    // state route
    Route::prefix('state')->group(function () {
        Route::post('/create', [ StateController::class, 'create']);
        Route::post('/retrieve/{id?}', [ StateController::class, 'fetch']);
        Route::post('/update/{id}', [ StateController::class, 'edit']);
        Route::delete('/delete/{id}', [ StateController::class, 'delete']);
    });
    
    // counter route
    Route::prefix('counter')->group(function () {
        Route::post('/create', [CounterController::class, 'create']);
        Route::post('/retrieve/{id?}', [CounterController::class, 'fetch']);
        Route::post('/update/{id}', [CounterController::class, 'edit']);
        Route::delete('/delete/{id}', [CounterController::class, 'delete']);
    });

    // template route
    Route::prefix('template')->group(function () {
        Route::post('/create', [TemplateController::class, 'create']);
        Route::post('/retrieve/{id?}', [TemplateController::class, 'fetch']);
        Route::post('/update/{id}', [TemplateController::class, 'edit']);
        Route::delete('/delete/{id}', [TemplateController::class, 'delete']);
    });

    // product route
    Route::prefix('product')->group(function () {
        Route::post('/create', [ProductsController::class, 'create']);
        Route::post('/retrieve/{id?}', [ProductsController::class, 'fetch']);
        Route::post('/update/{id}', [ProductsController::class, 'edit']);
        Route::delete('/delete/{id}', [ProductsController::class, 'delete']);
        Route::post('/import', [ProductsController::class, 'import']);
        Route::post('/export', [ProductStockController::class, 'export']);
    });

    // template route
    Route::prefix('template')->group(function () {
        Route::post('/create', [TemplateController::class, 'create']);
        Route::post('/retrieve/{id?}', [TemplateController::class, 'fetch']);
        Route::post('/update/{id}', [TemplateController::class, 'edit']);
        Route::delete('/delete/{id}', [TemplateController::class, 'delete']);
    });

    // product-stock route
    Route::prefix('product_stock')->group(function () {
        Route::post('/create', [ProductStockController::class, 'create']);
        Route::post('/retrieve/{id?}', [ProductStockController::class, 'fetch']);
        Route::post('/update/{id}', [ProductStockController::class, 'edit']);
        Route::delete('/delete/{id}', [ProductStockController::class, 'delete']);
        Route::post('/delete_attachment/{id}', [ProductStockController::class, 'deleteAttachment']);
        Route::post('/view_totals', [ProductStockController::class, 'fetchTotalsByBrandFinish']);
        Route::post('/import', [ProductStockController::class, 'import']);
        Route::post('/export', [ProductStockController::class, 'export']);
    });

    // logs route
    Route::prefix('logs')->group(function () {
        Route::post('/create', [TemplateController::class, 'create']);
    });

    // template route
    Route::prefix('template')->group(function () {
        Route::post('/create', [TemplateController::class, 'create']);
        Route::post('/retrieve/{id?}', [TemplateController::class, 'fetch']);
        Route::post('/update/{id}', [TemplateController::class, 'edit']);
        Route::delete('/delete/{id}', [TemplateController::class, 'delete']);
    });

    // clients route
    Route::prefix('clients')->group(function () {
        Route::post('/create', [ClientsController::class, 'create']);
        Route::post('/retrieve/{id?}', [ClientsController::class, 'fetch']);
        Route::post('/update/{id}', [ClientsController::class, 'edit']);
        Route::delete('/delete/{id}', [ClientsController::class, 'delete']);
        Route::post('/export', [ClientsController::class, 'exportExcel']);
    });

    // suppliers route
    Route::prefix('supplier')->group(function () {
        Route::post('/create', [SuppliersController::class, 'create']);
        Route::post('/retrieve/{id?}', [SuppliersController::class, 'fetch']);
        Route::post('/update/{id}', [SuppliersController::class, 'edit']);
        Route::delete('/delete/{id}', [SuppliersController::class, 'delete']);
        Route::post('/export', [SuppliersController::class, 'exportExcel']);
    });

    // pickup-cart route
    Route::prefix('pick_up_cart')->group(function () {
        Route::post('/create', [PickUpCartController::class, 'create']);
        Route::post('/retrieve/{id?}', [PickUpCartController::class, 'fetch']);
        Route::post('/update/{id}', [PickUpCartController::class, 'edit']);
        Route::delete('/delete/{id}', [PickUpCartController::class, 'delete']);
        Route::post('/retrieve_consolidated', [PickUpCartController::class, 'fetchMergedBySku']);
    });

    // pickup-slip route
    Route::prefix('pick_up_slip')->group(function () {
        Route::post('/create', [PickUpSlipController::class, 'create']);
        Route::post('/retrieve/{id?}', [PickUpSlipController::class, 'fetch']);
        Route::post('/update/{id}', [PickUpSlipController::class, 'edit']);
        Route::delete('/delete/{id}', [PickUpSlipController::class, 'delete']);
        Route::post('/create_to', [PickUpSlipController::class, 'addToSlip']);
    });

    // quotation route
    Route::prefix('quotation')->group(function () {
        Route::post('/create', [QuotationController::class, 'create']);
        Route::post('/retrieve/{id?}', [QuotationController::class, 'fetch']);
        Route::post('/update/{id}', [QuotationController::class, 'edit']);
        Route::delete('/delete/{id}', [QuotationController::class, 'delete']);
        Route::post('/export', [QuotationController::class, 'exportExcel']);
    });

    // proforma route
    Route::prefix('proforma')->group(function () {
        Route::post('/create', [ProformaController::class, 'create']);
        Route::post('/retrieve/{id?}', [ProformaController::class, 'fetch']);
        Route::post('/update/{id}', [ProformaController::class, 'edit']);
        Route::delete('/delete/{id}', [ProformaController::class, 'delete']);
        Route::post('/export', [ProformaController::class, 'exportExcel']);
    });

    // sales-order route
    Route::prefix('sales_order')->group(function () {
        Route::post('/create', [SalesOrderController::class, 'create']);
        Route::post('/retrieve/{id?}', [SalesOrderController::class, 'fetch']);
        Route::post('/update/{id}', [SalesOrderController::class, 'edit']);
        Route::delete('/delete/{id}', [SalesOrderController::class, 'delete']);
        Route::post('/export', [SalesOrderController::class, 'exportExcel']);
    });

    // sales-invoice route
    Route::prefix('sales-invoice')->group(function () {
        Route::post('/create', [SalesInvoiceController::class, 'create']);
        Route::post('/retrieve/{id?}', [SalesInvoiceController::class, 'fetch']);
        Route::post('/update/{id}', [SalesInvoiceController::class, 'edit']);
        Route::delete('/delete/{id}', [SalesInvoiceController::class, 'delete']);
        Route::post('/export', [SalesInvoiceController::class, 'exportExcel']);
    });

    // book-order route
    Route::prefix('book_order')->group(function () {
        Route::post('/create', [BookOrderController::class, 'create']);
        Route::post('/retrieve/{id?}', [BookOrderController::class, 'fetch']);
        Route::post('/update/{id}', [BookOrderController::class, 'edit']);
        Route::delete('/delete/{id}', [BookOrderController::class, 'delete']);
    });

    // purchase-order route
    Route::prefix('purchase_order')->group(function () {
        Route::post('/create', [PurchaseOrderController::class, 'create']);
        Route::post('/retrieve/{id?}', [PurchaseOrderController::class, 'fetch']);
        Route::post('/update/{id}', [PurchaseOrderController::class, 'edit']);
        Route::delete('/delete/{id}', [PurchaseOrderController::class, 'delete']);
    });

    // purchase-invoice route
    Route::prefix('purchase_invoice')->group(function () {
        Route::post('/create', [PurchaseInvoiceController::class, 'create']);
        Route::post('/retrieve/{id?}', [PurchaseInvoiceController::class, 'fetch']);
        Route::post('/update/{id}', [PurchaseInvoiceController::class, 'edit']);
        Route::delete('/delete/{id}', [PurchaseInvoiceController::class, 'delete']);
    });

    // purchase-bag route
    Route::prefix('purchase_bag')->group(function () {
        Route::post('/create', [PurchaseBagController::class, 'create']);
        Route::post('/retrieve/{id?}', [PurchaseBagController::class, 'fetch']);
        Route::post('/update/{id}', [PurchaseBagController::class, 'edit']);
        Route::delete('/delete/{id}', [PurchaseBagController::class, 'delete']);
    });

    // purchase-bag route
    Route::prefix('purchase_bag')->group(function () {
        Route::post('/create', [PurchaseBagController::class, 'create']);
        Route::post('/retrieve/{id?}', [PurchaseBagController::class, 'fetch']);
        Route::post('/update/{id}', [PurchaseBagController::class, 'edit']);
        Route::delete('/delete/{id}', [PurchaseBagController::class, 'delete']);
    });

    // grn route
    Route::prefix('grn')->group(function () {
        Route::post('/create', [GrnController::class, 'create']);
        Route::post('/retrieve/{id?}', [GrnController::class, 'fetch']);
        Route::post('/update/{id}', [GrnController::class, 'edit']);
        Route::delete('/delete/{id}', [GrnController::class, 'delete']);
    });

    // stock-transfer route
    Route::prefix('stock_transfer')->group(function () {
        Route::post('/create', [StockTransferController::class, 'create']);
        Route::post('/retrieve/{id?}', [StockTransferController::class, 'fetch']);
        Route::post('/update/{id}', [StockTransferController::class, 'edit']);
        Route::delete('/delete/{id}', [StockTransferController::class, 'delete']);
    });

    // master-views route
    Route::prefix('masters')->group(function () {
        Route::get('/grades', [MasterDataController::class, 'grades']);
        Route::get('/items', [MasterDataController::class, 'items']);
        Route::get('/sizes', [MasterDataController::class, 'sizes']);
        Route::get('/racks', [MasterDataController::class, 'racks']);
        Route::get('/finishes', [MasterDataController::class, 'finishes']);
        Route::get('/specifications', [MasterDataController::class, 'specifications']);
    });
});
