//
//{block name="backend/create_backend_order/store/payment"}
//
Ext.define('Shopware.apps.SwagBackendOrder.store.CustomerGroup', {
    /**
     * Extends the standard Ext Model
     * @string
     */
    extend: 'Ext.data.Store',

    model: 'Shopware.apps.SwagBackendOrder.model.CustomerGroup',

    alternateClassName: 'SwagBackendOrder.store.CustomerGroup',

    remoteSort: true,

    remoteFilter: true,

    pageSize: 50,

    batch: true,

    proxy: {
        type: 'ajax',

        api: {
            read: '{url action="getCustomerGroups"}'
        },

        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        }
    }
});
//
//{/block}