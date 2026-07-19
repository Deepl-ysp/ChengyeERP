<?php
// utils/image.php

/**
 * 订单图片生成器（含水印）
 * 
 * 修正内容：
 * - 修复金额小计计算（使用引用传递）
 * - 修复图片高度计算（增加底部留白）
 * - 底部仅显示备注、商品总数、总计金额
 * - 优化数值显示（保留两位小数）
 */
class Image
{
    protected $config;
    protected $fontFile;

    public function __construct(array $config = [])
    {
        if (empty($config)) {
            $config = include __DIR__ . '/../config/image.config.php';
        }
        $this->config = array_merge([
            'width' => 800,
            'bg_color' => [255, 255, 255],
            'padding' => 30,
            'line_height' => 28,
            'font_file' => __DIR__ . '/fonts/simhei.ttf',
            'title' => '销货清单',
            'title_font_size' => 24,
            'title_color' => [0, 0, 0],
            'info_font_size' => 14,
            'info_color' => [0, 0, 0],
            'table_header_height' => 30,
            'table_row_height' => 26,
            'table_font_size' => 13,
            'table_header_color' => [255, 255, 255],
            'table_header_bg' => [70, 130, 180],
            'table_border_color' => [0, 0, 0],
            'table_text_color' => [0, 0, 0],
            'table_col_widths' => [50, 220, 70, 70, 70, 100],
            'total_font_size' => 15,
            'total_color' => [200, 0, 0],
            'watermark' => [
                'text' => '',
                'font_size' => 40,
                'color' => [150, 150, 150, 60],
                'angle' => 20,
                'position' => 'bottom_right',
                'offset_x' => 20,
                'offset_y' => 20,
                'enabled' => true,
            ],
            'save_format' => 'png',
            'quality' => 90,
            'base_path' => __DIR__ . '/../top',
            'date_format' => 'Y.n.j',
        ], $config);

        $this->fontFile = $this->config['font_file'];
        if (!file_exists($this->fontFile)) {
            throw new \Exception("字体文件不存在: {$this->fontFile}");
        }
    }

public function generate(array $orderData, ?int $timestamp = null): string
{
    $orderData = $this->prepareOrderData($orderData);
    $height = $this->calculateHeight($orderData);

    $im = imagecreatetruecolor($this->config['width'], $height);

    // 开启 Alpha 混合（水印透明需要）
    imagesavealpha($im, true);
    imagealphablending($im, true);

    // ----- 背景填充（使用普通颜色，不透明）-----
    $bgColor = imagecolorallocate($im, 
        $this->config['bg_color'][0],
        $this->config['bg_color'][1],
        $this->config['bg_color'][2]
    );
    imagefilledrectangle($im, 0, 0, $this->config['width'], $height, $bgColor);

    // 后续绘制...（标题、表格、水印）
    $y = $this->config['padding'];
    $y = $this->drawTitle($im, $y);
    $y = $this->drawHeaderInfo($im, $y, $orderData);
    $y = $this->drawTable($im, $y, $orderData['items']);
    $y = $this->drawFooter($im, $y, $orderData);

    if ($this->config['watermark']['enabled']) {
        $this->addWatermark($im);
    }

    // 保存图片（强制 PNG）
    $dir = $this->getSaveDirectory($timestamp);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filePath = $dir . '/' . $orderData['order_id'] . '.png';
    imagepng($im, $filePath);

    if (PHP_VERSION_ID < 80500) imagedestroy($im);
    return $filePath;
}

    protected function prepareOrderData(array $data): array
    {
        $items = $data['items'] ?? [];
        $totalQty = 0;
        $totalAmount = 0;

        // 使用引用遍历，以便修改原数组
        foreach ($items as &$item) {
            $qty = floatval($item['quantity'] ?? 0);
            $price = floatval($item['price'] ?? 0);
            $subtotal = $qty * $price;
            $item['quantity'] = $qty;
            $item['price'] = $price;
            $item['subtotal'] = $subtotal;

            $totalQty += $qty;
            $totalAmount += $subtotal;
        }
        unset($item); // 解除引用

        $data['items'] = $items;
        $data['total_quantity'] = $data['total_quantity'] ?? $totalQty;
        $data['total_amount'] = $data['total_amount'] ?? $totalAmount;
        $data['remark'] = $data['remark'] ?? '无备注';

        return $data;
    }

    protected function calculateHeight(array $orderData): int
    {
        $padding = $this->config['padding'];
        $lineH = $this->config['line_height'];
        $titleH = 50; // 标题区域
        $infoLines = 5; // 头部信息行数（调整后）
        $headerH = $this->config['table_header_height'];
        $rowH = $this->config['table_row_height'];
        $rows = count($orderData['items']);
        $footerLines = 3; // 备注 + 商品总数 + 总计金额

        // 额外增加底部留白 40px
        $height = $padding * 2 + $titleH + $infoLines * $lineH + $headerH + $rows * $rowH + $footerLines * $lineH + 60;
        return max($height, 400);
    }

    protected function drawTitle($im, int $y): int
    {
        $color = imagecolorallocate($im, ...$this->config['title_color']);
        $fontSize = $this->config['title_font_size'];
        $text = $this->config['title'];
        $bbox = imagettfbbox($fontSize, 0, $this->fontFile, $text);
        $textWidth = $bbox[2] - $bbox[0];
        $x = ($this->config['width'] - $textWidth) / 2;
        imagettftext($im, $fontSize, 0, $x, $y + $fontSize, $color, $this->fontFile, $text);
        return $y + 40;
    }

    protected function drawHeaderInfo($im, int $y, array $data): int
    {
        $color = imagecolorallocate($im, ...$this->config['info_color']);
        $fontSize = $this->config['info_font_size'];
        $leftX = $this->config['padding'];
        $rightX = $this->config['width'] - $this->config['padding'];

        // 左列：客户单位、下单日期、收款单位、收货地址
        $leftLines = [
            "客户单位：{$data['customer']}",
            "下单日期：{$data['order_date']}",
            "收款单位：{$data['seller']}",
            "收货地址：{$data['address']}",
        ];
        // 右列：订单编号、联系电话、收货单位
        $rightLines = [
            "订单编号：{$data['order_id']}",
            "联系电话：{$data['phone']}",
            "收货单位：{$data['receiver']}",
        ];

        // 绘制左列
        foreach ($leftLines as $idx => $line) {
            $yPos = $y + ($idx + 1) * $this->config['line_height'];
            imagettftext($im, $fontSize, 0, $leftX, $yPos, $color, $this->fontFile, $line);
        }

        // 计算右列的最大宽度，以便右对齐
        $maxWidth = 0;
        foreach ($rightLines as $line) {
            $bbox = imagettfbbox($fontSize, 0, $this->fontFile, $line);
            $width = $bbox[2] - $bbox[0];
            if ($width > $maxWidth)
                $maxWidth = $width;
        }
        $rightStartX = $this->config['width'] - $this->config['padding'] - $maxWidth;

        // 绘制右列
        foreach ($rightLines as $idx => $line) {
            $yPos = $y + ($idx + 1) * $this->config['line_height'];
            imagettftext($im, $fontSize, 0, $rightStartX, $yPos, $color, $this->fontFile, $line);
        }

        $y += max(count($leftLines), count($rightLines)) * $this->config['line_height'] + 15;
        return $y;
    }

    protected function drawTable($im, int $y, array $items): int
    {
        $left = $this->config['padding'];
        $right = $this->config['width'] - $this->config['padding'];
        $colWidths = $this->config['table_col_widths'];
        $totalWidth = array_sum($colWidths);
        $scale = ($right - $left) / $totalWidth;
        $scaledWidths = array_map(function ($w) use ($scale) {
            return $w * $scale;
        }, $colWidths);

        $headerH = $this->config['table_header_height'];
        $rowH = $this->config['table_row_height'];
        $fontSize = $this->config['table_font_size'];

        $headerBg = imagecolorallocate($im, ...$this->config['table_header_bg']);
        $headerColor = imagecolorallocate($im, ...$this->config['table_header_color']);
        $borderColor = imagecolorallocate($im, ...$this->config['table_border_color']);
        $textColor = imagecolorallocate($im, ...$this->config['table_text_color']);

        // ------ 辅助函数：计算文本垂直居中的 Y 坐标 ------
        $getCenteredY = function ($text, $yTop, $cellHeight) use ($fontSize) {
            // 获取文本的实际高度（从基线顶部到底部）
            $bbox = imagettfbbox($fontSize, 0, $this->fontFile, $text);
            $textHeight = $bbox[3] - $bbox[5]; // bottom - top
            // 垂直居中：单元顶部 + (单元高度 - 文本高度) / 2 + 文本高度（因为基线在底部）
            return $yTop + ($cellHeight - $textHeight) / 2 + $textHeight;
        };

        // ------ 表头 ------
        $headers = ['序号', '商品名称', '单位', '数量', '单价', '金额小计'];
        $x = $left;
        imagefilledrectangle($im, $left, $y, $right, $y + $headerH, $headerBg);

        foreach ($headers as $i => $text) {
            $cellWidth = $scaledWidths[$i];
            $bbox = imagettfbbox($fontSize, 0, $this->fontFile, $text);
            $textWidth = $bbox[2] - $bbox[0];
            $textX = $x + ($cellWidth - $textWidth) / 2;
            $textY = $getCenteredY($text, $y, $headerH);
            imagettftext($im, $fontSize, 0, $textX, $textY, $headerColor, $this->fontFile, $text);
            imageline($im, $x, $y, $x, $y + $headerH, $borderColor);
            imageline($im, $x, $y + $headerH, $x + $cellWidth, $y + $headerH, $borderColor);
            $x += $cellWidth;
        }
        imageline($im, $x, $y, $x, $y + $headerH, $borderColor);
        $y += $headerH;

        // ------ 数据行 ------
        $rowIdx = 1;
        foreach ($items as $item) {
            $x = $left;
            $rowData = [
                $rowIdx++,
                $item['name'],
                $item['unit'],
                number_format($item['quantity'], 2),
                number_format($item['price'], 2),
                number_format($item['subtotal'], 2),
            ];
            foreach ($rowData as $i => $cell) {
                $cellWidth = $scaledWidths[$i];
                $bbox = imagettfbbox($fontSize, 0, $this->fontFile, $cell);
                $textWidth = $bbox[2] - $bbox[0];
                $textX = $x + ($cellWidth - $textWidth) / 2;
                $textY = $getCenteredY($cell, $y, $rowH);
                imagettftext($im, $fontSize, 0, $textX, $textY, $textColor, $this->fontFile, $cell);
                imageline($im, $x, $y, $x, $y + $rowH, $borderColor);
                imageline($im, $x, $y + $rowH, $x + $cellWidth, $y + $rowH, $borderColor);
                $x += $cellWidth;
            }
            imageline($im, $x, $y, $x, $y + $rowH, $borderColor);
            $y += $rowH;
        }

        return $y + 10;
    }

    protected function drawFooter($im, int $y, array $data): int
    {
        $color = imagecolorallocate($im, ...$this->config['total_color']);
        $fontSize = $this->config['total_font_size'];
        $left = $this->config['padding'];

        // 备注
        $remark = "备注：{$data['remark']}";
        imagettftext($im, $fontSize, 0, $left, $y + $fontSize, $color, $this->fontFile, $remark);
        $y += $this->config['line_height'];

        // 商品总数
        $totalQtyText = "商品总数：" . number_format($data['total_quantity'], 2);
        imagettftext($im, $fontSize, 0, $left, $y + $fontSize, $color, $this->fontFile, $totalQtyText);
        $y += $this->config['line_height'];

        // 总计金额（靠右）
        $totalAmountText = "总计金额：￥" . number_format($data['total_amount'], 2);
        $bbox = imagettfbbox($fontSize, 0, $this->fontFile, $totalAmountText);
        $textWidth = $bbox[2] - $bbox[0];
        $rightX = $this->config['width'] - $this->config['padding'] - $textWidth;
        imagettftext($im, $fontSize, 0, $rightX, $y + $fontSize, $color, $this->fontFile, $totalAmountText);

        return $y + $this->config['line_height'] + 20;
    }

protected function addWatermark($im)
{
    $cfg = $this->config['watermark'];
    if (!$cfg['enabled'] || empty($cfg['text'])) return;

    $text = $cfg['text'];
    $fontSize = $cfg['font_size'];
    $angle = $cfg['angle'];
    $color = $cfg['color']; // [R, G, B, Alpha]

    // 分配透明色（Alpha 0~127，这里强制使用配置）
    $alpha = isset($color[3]) ? $color[3] : 60;
    $watermarkColor = imagecolorallocatealpha($im, $color[0], $color[1], $color[2], $alpha);

    // 获取文字尺寸
    $bbox = imagettfbbox($fontSize, $angle, $this->fontFile, $text);
    $textWidth = $bbox[2] - $bbox[0];
    $textHeight = $bbox[1] - $bbox[7];

    $spacingX = $cfg['spacing_x'] ?? $textWidth * 2;
    $spacingY = $cfg['spacing_y'] ?? $textHeight * 1.5;

    $imgW = imagesx($im);
    $imgH = imagesy($im);

    // 平铺绘制
    for ($x = -$textWidth; $x < $imgW + $textWidth; $x += $spacingX) {
        for ($y = -$textHeight; $y < $imgH + $textHeight; $y += $spacingY) {
            imagettftext($im, $fontSize, $angle, $x, $y, $watermarkColor, $this->fontFile, $text);
        }
    }
}
    protected function getSaveDirectory(?int $timestamp): string
    {
        $date = $timestamp ? date($this->config['date_format'], $timestamp) : date($this->config['date_format']);
        return rtrim($this->config['base_path'], '/') . '/' . $date;
    }

    protected function saveImage($im, string $filePath): void
    {
        $format = $this->config['save_format'];
        if ($format === 'jpg' || $format === 'jpeg') {
            imagejpeg($im, $filePath, $this->config['quality']);
        } else {
            imagepng($im, $filePath);
        }
    }
}