export interface ConfirmSettings {
  customer: {
    delete: boolean;
  };
  customerManage: {
    deleteProduct: boolean;
  };
  finance: {
    deleteFlow: boolean;
  };
  order: {
    paymentExceed: boolean;
    updateStatus: boolean;
    delete: boolean;
  };
  supplier: {
    delete: boolean;
    deleteProduct: boolean;
  };
  warehouse: {
    deleteInventory: boolean;
    deleteWarehouse: boolean;
    confirmPurchase: boolean;
    completePurchase: boolean;
  };
  [module: string]: Record<string, boolean>;
}

export const confirmSettings: ConfirmSettings = {
  customer: {
    delete: false,
  },
  customerManage: {
    deleteProduct: false,
  },
  finance: {
    deleteFlow: false,
  },
  order: {
    paymentExceed: false,
    updateStatus: false,
    delete: false,
  },
  supplier: {
    delete: false,
    deleteProduct: false,
  },
  warehouse: {
    deleteInventory: false,
    deleteWarehouse: false,
    confirmPurchase: false,
    completePurchase: false,
  },
};

export const confirm = (message: string, key: `${string}.${string}`): boolean => {
  const [module, action] = key.split('.');
  if (!module || !action) return window.confirm(message);
  const moduleSettings = confirmSettings[module];
  if (moduleSettings && typeof moduleSettings[action] === 'boolean') {
    if (!moduleSettings[action]) {
      return true;
    }
  }
  return window.confirm(message);
};