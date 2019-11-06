//{block name="backend/create_backend_order/model/order_attribute"}
//
Ext.define('Shopware.apps.SwagBackendOrder.model.ShippingAttribute', {

    extend: 'Ext.data.Model',

    fields: [
        { name: 'title', type: 'string' },
        { name: 'company', type: 'string' },
        { name: 'firstname', type: 'string' },
        { name: 'lastname', type: 'string' },
        { name: 'street', type: 'string' },
        { name: 'address1', type: 'string' },
        { name: 'address2', type: 'string' },
        { name: 'postalcode', type: 'string' },
        { name: 'city', type: 'string' }
    ]
});
//
//{/block}
