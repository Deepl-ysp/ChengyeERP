const BASE_URL = "http://api.localhost:5734/v1/api";

async function fetchAndLog(url, options = {}) {
  const res = await fetch(url, options);
  const text = await res.text();
  console.log(`📄 [${options.method || "GET"}] ${url} 原始响应:`, text);
  try {
    const json = JSON.parse(text);
    console.log("✅ 解析为JSON:", json);
    return json;
  } catch {
    console.error("❌ 响应不是JSON，请检查后端错误");
    return null;
  }
}

async function testGetOrderList(page, size) {
  const url = `${BASE_URL}/order/getList/${page}/${size}`;
  await fetchAndLog(url);
}

async function testGetOrderDetails(orderId) {
  const url = `${BASE_URL}/order/getDetails/${orderId}`;
  await fetchAndLog(url);
}

async function testGetSupplierList(page, size) {
  const url = `${BASE_URL}/supplier/getList/${page}/${size}`;
  await fetchAndLog(url);
}

async function testPostSupplier(payload) {
  const url = `${BASE_URL}/supplier/set`;
  await fetchAndLog(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
}

async function runAllTests() {
  await testGetOrderList(1, 30);
  await testGetOrderDetails(100);
  await testGetSupplierList(2, 20);
  await testPostSupplier({ name: "测试供应商", contact: "test@example.com", phone: "1234567890" });
}

runAllTests();
