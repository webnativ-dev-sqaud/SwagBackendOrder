//
//{block name="backend/create_backend_order/store/order_attribute"}
//
Ext.define('Shopware.apps.SwagBackendOrder.store.ShippingAttribute', {

    extend: 'Ext.data.Store',

    model: 'Shopware.apps.SwagBackendOrder.model.ShippingAttribute',

    requires: [
        'Shopware.apps.SwagBackendOrder.model.ShippingAttribute'
    ],

    remoteSort: true,

    remoteFilter: true,

    pageSize: 50,

    batch: true
});
//
//{/block}