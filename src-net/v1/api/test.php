<?php
require_once __DIR__ . '/utils/image.php';

$orderSamples = [
    [
        'order_id'   => '1783505583857',
        'customer'   => '宋海军',
        'order_date' => '2026-07-08',
        'receiver'   => '宋海军',
        'address'    => '自提',
        'phone'      => '未填写',
        'seller'     => '义乌市越貌涂料商行',
        'remark'     => '无备注',
        'items'      => [
            ['name' => '立邦找平腻子粉', 'unit' => '包', 'quantity' => 10, 'price' => 18],
        ],
    ],
    [
        'order_id'   => '1783470944770',
        'customer'   => '久圣装饰江',
        'order_date' => '2026-07-08',
        'receiver'   => '久圣装饰江总',
        'address'    => '曲江映月25-2305',
        'phone'      => '未填写',
        'seller'     => '义乌市越貌涂料商行',
        'remark'     => '无备注',
        'items'      => [
            ['name' => '阴角线', 'unit' => '根', 'quantity' => 100, 'price' => 0.8],
            ['name' => '阳角线', 'unit' => '根', 'quantity' => 100, 'price' => 0.8],
            ['name' => '立邦拉法基', 'unit' => '包', 'quantity' => 25, 'price' => 18],
            ['name' => '立邦面层腻子粉', 'unit' => '包', 'quantity' => 20, 'price' => 20],
            ['name' => '立邦找平腻子粉', 'unit' => '包', 'quantity' => 25, 'price' => 20],
            ['name' => '10公分网', 'unit' => '卷', 'quantity' => 5, 'price' => 8],
        ],
    ],
];

try {
    $image = new Image();
} catch (Exception $e) {
    die('初始化失败：' . $e->getMessage());
}

echo "<p>图片存储目录：<code>" . __DIR__ . "/top/</code></p>";

foreach ($orderSamples as $index => $data) {
    try {
        $filePath = $image->generate($data);
        $fileName = basename($filePath);
        $relativePath = str_replace(__DIR__, '', $filePath);
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
        echo "<div style='border:1px solid #ccc; margin:20px 0; padding:10px;'>";
        echo "<h3>订单：{$data['order_id']}</h3>";
        echo "<p>生成成功：<code>{$filePath}</code></p>";
        echo "<img src='/{$relativePath}' style='max-width:100%; border:1px solid #ddd;' alt='订单图片' />";
        echo "</div>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>订单 {$data['order_id']} 生成失败：" . $e->getMessage() . "</p>";
    }
}

if (!is_writable(__DIR__ . '/top')) {
    echo "<p style='color:orange;'>⚠️ 提示：<code>/top</code> 目录不可写，请检查权限。</p>";
}