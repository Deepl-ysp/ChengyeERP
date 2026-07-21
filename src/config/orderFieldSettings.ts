// 订单字段渲染配置（默认值）
// 用于创建订单页"更多设置"弹窗中控制哪些字段在预览/打印中渲染
// 用户可在弹窗中临时调整，不影响此默认配置

export interface OrderFieldItem {
  key: string;
  label: string;
  visible: boolean;
}

export interface OrderFieldSettings {
  // 订单信息区字段
  info: OrderFieldItem[];
  // 商品表格列
  columns: OrderFieldItem[];
  // 其他区块（合计行、底部信息、签字行等）
  sections: OrderFieldItem[];
}

export const orderFieldSettings: OrderFieldSettings = {
  info: [
    { key: 'customer_name', label: '客户单位', visible: true },
    { key: 'seller_name', label: '收款单位', visible: true },
    { key: 'receiver_name', label: '收货单位', visible: true },
    { key: 'receiver_phone', label: '联系电话', visible: true },
    { key: 'receiver_address', label: '收货地址', visible: true },
    { key: 'create_time', label: '创建时间', visible: true },
  ],
  columns: [
    { key: 'index', label: '序号', visible: true },
    { key: 'product_name', label: '商品名称', visible: true },
    { key: 'specification', label: '规格', visible: false },
    { key: 'unit', label: '单位', visible: true },
    { key: 'quantity', label: '数量', visible: true },
    { key: 'unit_price', label: '单价', visible: true },
    { key: 'subtotal', label: '金额小计', visible: true },
  ],
  sections: [
    { key: 'total_row', label: '合计行', visible: true },
    { key: 'footer_seller_address', label: '收款单位地址', visible: false },
    { key: 'footer_seller_phone', label: '收款单位电话', visible: false },
    { key: 'footer_remark', label: '底部备注', visible: true },
    { key: 'signature_row', label: '签字行', visible: false },
  ],
};
