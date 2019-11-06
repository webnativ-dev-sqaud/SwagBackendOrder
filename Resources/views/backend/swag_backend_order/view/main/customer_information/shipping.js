//
//{block name="backend/create_backend_order/view/customer_information/shipping"}
//
Ext.define('Shopware.apps.SwagBackendOrder.view.main.CustomerInformation.Shipping', {

    extend: 'Ext.panel.Panel',

    alternateClassName: 'SwagBackendOrder.view.main.CustomerInformation.Shipping',

    alias: 'widget.createbackendorder-customer-shipping',

    bodyPadding: 10,

    flex: 2,

    autoScroll: true,

    paddingRight: 5,

    snippets: {
        title: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/shipping/title"}Payment{/s}',
        billingAsShipping: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/shipping/billing_as_shipping"}Use billing address{/s}',
        salutation: {
            mister: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/salutation/mister"}Mr{/s}',
            miss: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/salutation/miss"}Ms{/s}'
        },
        shippingInformation: {
            salutationLabel: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/salutation/label"}Anrede{/s}',
            titelLabel: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/Titel/label"}Titel{/s}',
            firmaLabel: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/Firma/label"}Firma{/s}',
            firstNameLabel: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/firstname/label"}Vorname{/s}',
            lastNameLabel: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/lastname/label"}Nachname*{/s}',
            streetLabel: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/street/label"}Straße{/s}',
            address1Label: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/address1/label"}Adresszustz 1{/s}',
            address2Label: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/address2/label"}Adresszustz 2{/s}',
            postalcodeLabel: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/postalcode/label"}Postleizahl*{/s}',
            cityLabel: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/city/label"}Stadt*{/s}',
            countryLabel: '{s namespace="backend/swag_backend_order/view/customer_information" name="swag_backend_order/customer_information/country/label"}Land*{/s}'
        }
    },

    initComponent: function () {
        var me = this;

        me.title = me.snippets.title;

        /**
         * gets the customer store and loads the selected customer
         */
        me.customerStore = me.subApplication.getStore('Customer');
        me.customerStore.on('load', function () {
            if (Ext.isObject(me.customerStore) && me.customerStore.count() == 1) {
                me.shippingStore = me.customerStore.getAt(0).shipping();
                me.shippingAddressComboBox.bindStore(me.shippingStore);
            }

            me.resetFields();
        });

        me.items = me.createShippingContainer();

        me.billingAsShippingCheckbox.on('change', function () {
            if (me.billingAsShippingCheckbox.getValue()) {
                me.shippingAddressComboBox.setValue('');
                me.remove('shippingDataView', true);
                me.doLayout();

                me.fireEvent('selectBillingAsShippingAddress');
            }
        });

        me.callParent(arguments);
    },

    registerEvents: function () {
        this.addEvents(
            'selectShippingAddress'
        );
    },

    createShippingContainer: function () {
        var me = this;

        return Ext.create('Ext.container.Container', {
            layout: 'hbox',
            items: me.createShippingItems()
        });
    },

    createShippingItems: function () {
        var me = this;

        /**
         * @TODO: renderer for the display field, correct value field
         */
        me.shippingAddressComboBox = Ext.create('Ext.form.field.ComboBox', {
            name: 'shippingAddresses',
            queryMode: 'local',
            store: me.shippingStore,
            flex: 1,
            disabled: true,
            displayField: 'displayField',
            valueField: 'displayField',
            listConfig: {
                maxHeight: 200
            },
            width: 250,
            tpl: me.createShippingAddressComboTpl(),
            listeners: {
                'change': function (comboBox, value) {
                    var record = this.findRecordByValue(value);

                    me.fireEvent('selectShippingAddress', record);

                    if (record === false) {
                        // Do nothing if there is no corresponding record.
                        return;
                    }

                    me.billingAsShippingCheckbox.setValue(false);

                    var shippingAddressTemplateStore = Ext.create('Ext.data.Store', {
                        model: 'Shopware.apps.SwagBackendOrder.model.Address',
                        data: record.data
                    });

                    me.dataView = Ext.create('Ext.view.View', {
                        id: 'shippingDataView',
                        name: 'shippingDataView',
                        store: shippingAddressTemplateStore,
                        tpl: me.createShippingTemplate(),
                        layout: 'fit',
                        padding: '5 0 0 0'
                    });

                    me.remove('shippingDataView', true);
                    me.add(me.dataView);
                    me.doLayout();
                }
            }
        });

        me.billingAsShippingCheckbox = Ext.create('Ext.form.field.Checkbox', {
            flex: 1,
            boxLabel: me.snippets.billingAsShipping,
            name: 'billingAsShipping',
            id: 'billingAsShippingCheckBox',
            inputValue: true,
            uncheckedValue: false,
            checked: true,
            height: 35,
            width: 250,
            listeners: {
                change: function (field, value) {
                    if (value) {
                        me.shippingAddressComboBox.disable();
                    } else {
                        me.shippingAddressComboBox.enable();
                        me.shippingAddressComboBox.setValue(me.shippingStore.getAt(0).get(me.shippingAddressComboBox.valueField));
                    }
                }
            }
        });

        var salutations = Ext.create('Ext.data.Store', {
            fields : ['abbr', 'name'],
            data : [
                { "abbr": "mr", "name": "Herr" },
                { "abbr": "ms", "name": "Frau" }
            ]
        });

        var countries = Ext.create('Ext.data.Store', {
            fields : ['abbr', 'name'],
            data : [
                { "abbr": "2", "name": "Deutschland" }
            ]
        });

        var salutationTxtBox = Ext.create('Ext.form.field.ComboBox', {
            name: 'salutationTxtBox',
            queryMode: 'local',
            store: salutations,
            flex: 1,
            width: 400,
            listConfig: {
                maxHeight: 200
            },
            allowBlank: true,
            valueField: 'abbr',
            fieldLabel: me.snippets.shippingInformation.salutationLabel,
            tpl: me.createSalutationCountryComboTpl(0),
            displayTpl: me.createSalutationCountryComboTpl(1),
            listeners: {
                'change': function (comboBox, record) {
                    me.fireEvent('changeShippingFieldDP', comboBox, record);
                }
            }
        });

        var countryTxtBox = Ext.create('Ext.form.field.ComboBox', {
            name: 'countryTxtBox',
            queryMode: 'local',
            store: countries,
            flex: 1,
            width: 400,
            listConfig: {
                maxHeight: 200
            },
            allowBlank: true,
            valueField: 'abbr',
            fieldLabel: me.snippets.shippingInformation.countryLabel,
            tpl: me.createSalutationCountryComboTpl(0),
            displayTpl: me.createSalutationCountryComboTpl(1),
            listeners: {
                'change': function (comboBox, record) {
                    me.fireEvent('changeShippingFieldDP', comboBox, record);
                }
            }
        });

        var titleTxtBox = Ext.create('Ext.form.TextField', {
            name: 'titleTxtBox',
            width: 400,
            fieldLabel: me.snippets.shippingInformation.titelLabel,
            maxLength: 30,
            enforceMaxLength: true,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeShippingField', field, newValue, oldValue);
                }
            }
        });

        var firmaTxtBox = Ext.create('Ext.form.TextField', {
            name: 'firmaTxtBox',
            width: 400,
            fieldLabel: me.snippets.shippingInformation.firmaLabel,
            maxLength: 30,
            enforceMaxLength: true,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeShippingField', field, newValue, oldValue);
                }
            }
        });

        var firstNameTxtBox = Ext.create('Ext.form.TextField', {
            name: 'firstNameTxtBox',
            width: 400,
            fieldLabel: me.snippets.shippingInformation.firstNameLabel,
            maxLength: 30,
            enforceMaxLength: true,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeShippingField', field, newValue, oldValue);
                }
            }
        });

        var lastNameTxtBox = Ext.create('Ext.form.TextField', {
            name: 'lastNameTxtBox',
            width: 400,
            fieldLabel: me.snippets.shippingInformation.lastNameLabel,
            maxLength: 30,
            enforceMaxLength: true,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeShippingField', field, newValue, oldValue);
                }
            }
        });

        var streetTxtBox = Ext.create('Ext.form.TextField', {
            name: 'streetTxtBox',
            width: 400,
            fieldLabel: me.snippets.shippingInformation.streetLabel,
            maxLength: 30,
            enforceMaxLength: true,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeShippingField', field, newValue, oldValue);
                }
            }
        });

        var address1TxtBox = Ext.create('Ext.form.TextField', {
            name: 'address1TxtBox',
            width: 400,
            fieldLabel: me.snippets.shippingInformation.address1Label,
            maxLength: 255,
            enforceMaxLength: true,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeShippingField', field, newValue, oldValue);
                }
            }
        });

        var address2TxtBox = Ext.create('Ext.form.TextField', {
            name: 'address2TxtBox',
            width: 400,
            fieldLabel: me.snippets.shippingInformation.address2Label,
            maxLength: 255,
            enforceMaxLength: true,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeShippingField', field, newValue, oldValue);
                }
            }
        });

        var postalCodeTxtBox = Ext.create('Ext.form.TextField', {
            name: 'postalCodeTxtBox',
            width: 400,
            fieldLabel: me.snippets.shippingInformation.postalcodeLabel,
            maxLength: 10,
            enforceMaxLength: true,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeShippingField', field, newValue, oldValue);
                }
            }
        });

        var cityTxtBox = Ext.create('Ext.form.TextField', {
            name: 'cityTxtBox',
            width: 400,
            fieldLabel: me.snippets.shippingInformation.cityLabel,
            maxLength: 25,
            enforceMaxLength: true,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeShippingField', field, newValue, oldValue);
                }
            }
        });

        var shippingInformationContainer = Ext.create('Ext.Container', {
            name: 'additionalInformationContainer',
            width: 400,
            height: 'auto',
            items: [
                salutationTxtBox,
                titleTxtBox,
                firmaTxtBox,
                firstNameTxtBox,
                lastNameTxtBox,
                streetTxtBox,
                address1TxtBox,
                address2TxtBox,
                postalCodeTxtBox,
                cityTxtBox,
                countryTxtBox
            ]
        });

        var shippingAddressBoxContainer = Ext.create('Ext.Container', {
            name: 'additionalInformationContainer',
            width: 400,
            height: 'auto',
            items: [
                me.shippingAddressComboBox,
                me.billingAsShippingCheckbox
            ]
        });

        me.shippingAddressBox = Ext.create('Ext.container.Container', {
            layout: 'hbox',
            flex: 2,
            title: 'right',
            padding: '10 0 0 10',
            autoHeight: true,
            items: [
                shippingAddressBoxContainer
            ]
        });

        me.shippingInformationContainerParent = Ext.create('Ext.container.Container', {
            layout: 'hbox',
            flex: 3,
            title: 'left',
            padding: '10 0 0 10',
            autoHeight: true,
            items: [
                shippingInformationContainer
            ]
        });

        return [me.shippingInformationContainerParent, me.shippingAddressBox];
    },

    createSalutationCountryComboTpl: function (temp) {
        var me = this;

        if (temp === 0) {
            return new Ext.XTemplate(
                '{literal}<tpl for=".">',
                '<div class= "x-combo-list-item x-boundlist-item">',
                '{name}',
                '</div>',
                '</tpl>{/literal}'
            );
        } else {
            return new Ext.XTemplate(
                '{literal}<tpl for=".">',
                    '{name}',
                '</tpl>{/literal}'
            );
        }
    },

    createShippingAddressComboTpl: function () {
        var me = this;

        return new Ext.XTemplate(
            '{literal}<tpl for=".">',
            '<div class= "x-combo-list-item x-boundlist-item">',
            '<tpl if="company">',
            '{company},<br/>',
            '</tpl>',
            '<tpl switch="salutation">',
            '<tpl case="mr">',
            me.snippets.salutation.mister + ' ',
            '<tpl case="ms">',
            me.snippets.salutation.miss + ' ',
            '</tpl>',
            '{firstname} {lastname},<br/>{zipcode} {city},<br/>{street}',
            '<tpl if="state">',
            ',<br/>{state}',
            '</tpl>',
            '<tpl if="country">',
            ',<br/>{country}',
            '</tpl>',
            '</div>',
            '</tpl>{/literal}'
        );
    },

    createShippingTemplate: function () {
        var me = this;

        return new Ext.XTemplate(
            '{literal}<tpl for=".">',
            '<div class="customeer-info-pnl">',
            '<div class="base-info">',
            '<p>',
            '<span>{company}</span>',
            '</p>',
            '<p>',
            '<tpl switch="salutation">',
            '<tpl case="mr">',
            me.snippets.salutation.mister + ' ',
            '<tpl case="ms">',
            me.snippets.salutation.miss + ' ',
            '</tpl>',
            '<span>{firstname}</span>&nbsp;',
            '<span>{lastname}</span>',
            '</p>',
            '<p>',
            '<span>{street}</span>',
            '</p>',
            '<tpl if="additionalAddressLine1">',
            '<p>',
            '<span>{additionalAddressLine1}</span>',
            '</p>',
            '</tpl>',
            '<tpl if="additionalAddressLine2">',
            '<p>',
            '<span>{additionalAddressLine1}</span>',
            '</p>',
            '</tpl>',
            '<p>',
            '<span>{zipcode}</span>&nbsp;',
            '<span>{city}</span>',
            '</p>',
            '<p>',
            '<span>{state}</span>',
            '</p>',
            '<p>',
            '<span>{country}</span>',
            '</p>',
            '</div>',
            '</div>',
            '</tpl>{/literal}'
        );
    },

    resetFields: function () {
        var me = this;

        me.shippingAddressComboBox.setValue('');
        me.remove('shippingDataView', true);
        me.doLayout();
    }
});
//
//{/block}