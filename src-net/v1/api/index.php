<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/config/pdo.php';
require_once __DIR__ . '/utils/get.php';
require_once __DIR__ . '/utils/set.php';
require_once __DIR__ . '/utils/update.php';

$getter = new \DbGet($pdo);
$setter = new \DbSet($pdo);
$updatter = new \DbUpdate($pdo);

$router = new \Mezon\Router\Router();

// ===== 订单管理路由 =====
require_once __DIR__ . '/order/get.php';
$getterOrder = new \GetterOrder($getter);
$router->addRoute("/order/getList", [$getterOrder, "getList"]);

require_once __DIR__ . '/order/getDetail.php';
$getterOrderDetail = new \GetterOrderDetail($getter);
$router->addRoute("/order/getDetail", [$getterOrderDetail, "getDetail"]);

require_once __DIR__ . '/order/set.php';
$setterOrder = new \SetterOrder($setter, $getter);
$router->addRoute("/order/set", [$setterOrder, "setOrder"], "POST");

require_once __DIR__ . '/order/delete.php';
$deleterOrder = new \DeleterOrder($setter);
$router->addRoute("/order/delete", [$deleterOrder, "deleteOrder"], "DELETE");

require_once __DIR__ . '/order/update.php';
$updaterOrder = new \UpdaterOrder($setter, $getter);
$router->addRoute("/order/update", [$updaterOrder, "updateOrder"], "POST");

require_once __DIR__ . '/order/updateStatus.php';
$updaterOrderStatus = new \UpdaterOrderStatus($setter);
$router->addRoute("/order/updateStatus", [$updaterOrderStatus, "updateStatus"], "POST");

require_once __DIR__ . '/order/payment/set.php';
$setterOrderPayment = new \SetterOrderPayment($setter, $getter);
$router->addRoute("/order/payment/set", [$setterOrderPayment, "setPayment"], "POST");

require_once __DIR__ . '/supplier/get.php';
$getterSupplier = new \GetterSupplier($getter);
$router->addRoute("/supplier/getList/[i:page]/[i:size]", [$getterSupplier, "getList"]);

require_once __DIR__ . '/supplier/set.php';
$setterSupplier = new \SetterSupplier($setter);
$router->addRoute("/supplier/set",[$setterSupplier,"setSupplier"],"POST");

require_once __DIR__ . '/supplier/delete.php';
$deleteSupplier = new \DeleteSupplier($setter);
$router->addRoute("/supplier/delete", [$deleteSupplier, "deleteSupplier"], "DELETE");

require_once __DIR__ . '/supplier/updateStatus.php';
$updateSupplierStatus = new \UpdateSupplierStatus($setter);
$router->addRoute("/supplier/updateStatus", [$updateSupplierStatus, "updateStatus"], "POST");

require_once __DIR__ . '/supplier/quote/get.php';
$getterQuote = new \GetterQuote($getter);
$router->addRoute("/supplier/quote/getList", [$getterQuote, "getList"]);

require_once __DIR__ . '/supplier/quote/set.php';
$setterQuote = new \SetterQuote($setter);
$router->addRoute("/supplier/quote/set", [$setterQuote, "setQuote"], "POST");

require_once __DIR__ . '/supplier/quote/delete.php';
$deleteQuote = new \DeleteQuote($setter);
$router->addRoute("/supplier/quote/delete", [$deleteQuote, "deleteQuote"], "DELETE");

require_once __DIR__ . '/supplier/quote/getForSelect.php';
$getterQuoteSelect = new \GetterQuoteForSelect($getter);
$router->addRoute("/supplier/quote/getForSelect", [$getterQuoteSelect, "getForSelect"]);

require_once __DIR__ . '/supplier/getForSelect.php';
$getterSupplierSelect = new \GetterSupplierForSelect($getter);
$router->addRoute("/supplier/getForSelect", [$getterSupplierSelect, "getForSelect"]);

// ===== 客户管理路由 =====
require_once __DIR__ . '/customer/get.php';
$getterCustomer = new \GetterCustomer($getter);
$router->addRoute("/customer/getList/[i:page]/[i:size]", [$getterCustomer, "getList"]);

require_once __DIR__ . '/customer/set.php';
$setterCustomer = new \SetterCustomer($setter);
$router->addRoute("/customer/set",[$setterCustomer,"setCustomer"],"POST");

require_once __DIR__ . '/customer/delete.php';
$deleteCustomer = new \DeleteCustomer($setter);
$router->addRoute("/customer/delete", [$deleteCustomer, "deleteCustomer"], "DELETE");

require_once __DIR__ . '/customer/updateStatus.php';
$updateCustomerStatus = new \UpdateCustomerStatus($setter);
$router->addRoute("/customer/updateStatus", [$updateCustomerStatus, "updateStatus"], "POST");

require_once __DIR__ . '/customer/getForSelect.php';
$getterCustomerSelect = new \GetterCustomerForSelect($getter);
$router->addRoute("/customer/getForSelect", [$getterCustomerSelect, "getForSelect"]);

require_once __DIR__ . '/customer/price/get.php';
$getterCustomerPrice = new \GetterCustomerPrice($getter);
$router->addRoute("/customer/price/getList", [$getterCustomerPrice, "getList"]);

require_once __DIR__ . '/customer/price/set.php';
$setterCustomerPrice = new \SetterCustomerPrice($setter);
$router->addRoute("/customer/price/set", [$setterCustomerPrice, "setPrice"], "POST");

require_once __DIR__ . '/customer/price/delete.php';
$deleteCustomerPrice = new \DeleteCustomerPrice($setter);
$router->addRoute("/customer/price/delete", [$deleteCustomerPrice, "deletePrice"], "DELETE");

// ===== 仓库管理路由 =====
require_once __DIR__ . '/warehouse/get.php';
$getterWarehouse = new \GetterWarehouse($getter);
$router->addRoute("/warehouse/getList/[i:page]/[i:size]", [$getterWarehouse, "getList"]);

require_once __DIR__ . '/warehouse/set.php';
$setterWarehouse = new \SetterWarehouse($setter);
$router->addRoute("/warehouse/set", [$setterWarehouse, "setWarehouse"], "POST");

require_once __DIR__ . '/warehouse/delete.php';
$deleteWarehouse = new \DeleteWarehouse($setter);
$router->addRoute("/warehouse/delete", [$deleteWarehouse, "deleteWarehouse"], "DELETE");

require_once __DIR__ . '/warehouse/updateStatus.php';
$updateWarehouseStatus = new \UpdateWarehouseStatus($setter);
$router->addRoute("/warehouse/updateStatus", [$updateWarehouseStatus, "updateStatus"], "POST");

require_once __DIR__ . '/warehouse/getForSelect.php';
$getterWarehouseSelect = new \GetterWarehouseForSelect($getter);
$router->addRoute("/warehouse/getForSelect", [$getterWarehouseSelect, "getForSelect"]);

require_once __DIR__ . '/warehouse/inventory/get.php';
$getterWarehouseInventory = new \GetterWarehouseInventory($getter);
$router->addRoute("/warehouse/inventory/getList", [$getterWarehouseInventory, "getList"]);

require_once __DIR__ . '/warehouse/inventory/set.php';
$setterWarehouseInventory = new \SetterWarehouseInventory($setter);
$router->addRoute("/warehouse/inventory/set", [$setterWarehouseInventory, "setInventory"], "POST");

require_once __DIR__ . '/warehouse/inventory/delete.php';
$deleteWarehouseInventory = new \DeleteWarehouseInventory($setter);
$router->addRoute("/warehouse/inventory/delete", [$deleteWarehouseInventory, "deleteInventory"], "DELETE");

require_once __DIR__ . '/warehouse/inventory/getProducts.php';
$getterWarehouseProducts = new \GetterWarehouseProducts($getter);
$router->addRoute("/warehouse/inventory/getProducts", [$getterWarehouseProducts, "getProducts"]);

require_once __DIR__ . '/warehouse/inventory/searchProducts.php';
$getterWarehouseSearchProducts = new \GetterWarehouseSearchProducts($getter);
$router->addRoute("/warehouse/inventory/searchProducts", [$getterWarehouseSearchProducts, "searchProducts"]);

require_once __DIR__ . '/warehouse/inventory/checkProduct.php';
$getterWarehouseCheckProduct = new \GetterWarehouseCheckProduct($getter);
$router->addRoute("/warehouse/inventory/checkProduct", [$getterWarehouseCheckProduct, "checkProduct"]);

require_once __DIR__ . '/warehouse/io/get.php';
$getterWarehouseIO = new \GetterWarehouseIO($getter);
$router->addRoute("/warehouse/io/getList", [$getterWarehouseIO, "getList"]);

require_once __DIR__ . '/warehouse/io/set.php';
$setterWarehouseIO = new \SetterWarehouseIO($setter, $getter);
$router->addRoute("/warehouse/io/set", [$setterWarehouseIO, "setIO"], "POST");

require_once __DIR__ . '/warehouse/purchase/get.php';
$getterWarehousePurchase = new \GetterWarehousePurchase($getter);
$router->addRoute("/warehouse/purchase/getList", [$getterWarehousePurchase, "getList"]);
$router->addRoute("/warehouse/purchase/getDetail", [$getterWarehousePurchase, "getDetail"]);

require_once __DIR__ . '/warehouse/purchase/set.php';
$setterWarehousePurchase = new \SetterWarehousePurchase($setter, $getter);
$router->addRoute("/warehouse/purchase/set", [$setterWarehousePurchase, "setPurchase"], "POST");
$router->addRoute("/warehouse/purchase/confirm", [$setterWarehousePurchase, "confirmPurchase"], "POST");
$router->addRoute("/warehouse/purchase/complete", [$setterWarehousePurchase, "completePurchase"], "POST");

// ===== 财务管理路由 =====
require_once __DIR__ . '/finance/flow/get.php';
$getterFinanceFlow = new \GetterFinanceFlow($getter);
$router->addRoute("/finance/flow/getList", [$getterFinanceFlow, "getList"]);
$router->addRoute("/finance/flow/getOverview", [$getterFinanceFlow, "getOverview"]);

require_once __DIR__ . '/finance/flow/set.php';
$setterFinanceFlow = new \SetterFinanceFlow($setter, $getter);
$router->addRoute("/finance/flow/set", [$setterFinanceFlow, "setFlow"], "POST");

require_once __DIR__ . '/finance/flow/delete.php';
$deleterFinanceFlow = new \SetterFinanceFlowDelete($setter, $getter);
$router->addRoute("/finance/flow/delete", [$deleterFinanceFlow, "deleteFlow"], "DELETE");

require_once __DIR__ . '/finance/receivable/get.php';
$getterFinanceReceivable = new \GetterFinanceReceivable($getter);
$router->addRoute("/finance/receivable/getList", [$getterFinanceReceivable, "getList"]);
$router->addRoute("/finance/receivable/getByCustomer", [$getterFinanceReceivable, "getByCustomer"]);
$router->addRoute("/finance/receivable/getReconciliation", [$getterFinanceReceivable, "getReconciliation"]);

require_once __DIR__ . '/finance/payable/get.php';
$getterFinancePayable = new \GetterFinancePayable($getter);
$router->addRoute("/finance/payable/getList", [$getterFinancePayable, "getList"]);

require_once __DIR__ . '/finance/reconciliation/get.php';
$getterFinanceReconciliation = new \GetterFinanceReconciliation($getter);
$router->addRoute("/finance/reconciliation/getList", [$getterFinanceReconciliation, "getList"]);
$router->addRoute("/finance/reconciliation/getDetail", [$getterFinanceReconciliation, "getDetail"]);

require_once __DIR__ . '/finance/reconciliation/set.php';
$setterFinanceReconciliation = new \SetterFinanceReconciliation($setter, $getter);
$router->addRoute("/finance/reconciliation/reconcile", [$setterFinanceReconciliation, "reconcile"], "POST");

require_once __DIR__ . '/finance/report/get.php';
$getterFinanceReport = new \GetterFinanceReport($getter);
$router->addRoute("/finance/report/getProfit", [$getterFinanceReport, "getProfit"]);

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/v1/api';

if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

if (empty($requestUri)) {
    $requestUri = '/';
}

$result = $router->callRoute($requestUri);

if (is_array($result) || is_object($result)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} else {
    echo $result;
}