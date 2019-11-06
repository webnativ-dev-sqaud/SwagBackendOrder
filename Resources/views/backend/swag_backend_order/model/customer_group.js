//{block name="backend/create_backend_order/model/payment"}
//
Ext.define('Shopware.apps.SwagBackendOrder.model.CustomerGroup', {
    /**
     * Extends the standard Ext Model
     * @string
     */
    extend: 'Ext.data.Model',

    /**
     * define an alternate class name for an easier identification
     */
    alternateClassName: 'SwagBackendOrder.model.CustomerGroup',

    /**
     * The fields used for this model
     * @array
     */
    fields: [
        //{block name="backend/base/model/payment/fields"}{/block}
        { name: 'id', type: 'string' },
        { name: 'name', type: 'string' }
    ]
});
//
//{/block}