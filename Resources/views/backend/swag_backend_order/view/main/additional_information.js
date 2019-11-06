//
//{block name="backend/create_backend_order/view/additional_information"}
//
Ext.define('Shopware.apps.SwagBackendOrder.view.main.AdditionalInformation', {

    extend: 'Ext.panel.Panel',

    alternateClassName: 'SwagBackendOrder.view.main.AdditionalInformation',

    alias: 'widget.createbackendorder-additional',

    flex: 1,

    layout: 'hbox',

    margin: '15 10 0 5',

    overflowY: 'auto',

    snippets: {
        title: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/title"}Additional Information{/s}',
        additionalInformation: {
            selectCustomerGroup: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/selectCustomerGroup/label"}Kundengruppe auswählen{/s}',
			orderfield1Label: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/orderfield1/label"}Zusatzfeld Anschrift{/s}',
            orderfield2Label: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/orderfield2/label"}Referenz{/s}',
            orderfield3Label: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/orderfield3/label"}Auftragsart*{/s}',
            orderfield4Label: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/orderfield4/label"}Adressgruppe*{/s}',
            orderfield5Label: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/orderfield5/label"}Wunsch-Lieferdatum{/s}',
            attribute1Label: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/attribute1/label"}Attribute 1{/s}',
            attribute2Label: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/attribute2/label"}Attribute 2{/s}',
            attribute3Label: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/attribute3/label"}Attribute 3{/s}',
            attribute4Label: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/attribute4/label"}Attribute 4{/s}',
            attribute5Label: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/attribute5/label"}Attribute 5{/s}',
            attribute6Label: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/attribute6/label"}Attribute 6{/s}',
            desktopType: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/device_type/label"}Device-Type{/s}',
            desktopTypeHelpText: '{s namespace="backend/swag_backend_order/view/additional_information" name="swag_backend_order/additional/device_type/help_text"}The device type determines via which communication channel the order has been placed, for example fax, telephone or personally in your local store. The turnover by device types can be viewed in the statistics under "Turnover by device type".{/s}'
        }
    },

    initComponent: function () {
        var me = this;

        me.title = me.snippets.title;
        me.items = me.createAdditionalInformationItems();

        me.callParent(arguments);
    },

    createAdditionalInformationItems: function () {
        var me = this;

        return [me.createAdditionalInformationContainerLeft(), me.createAdditionalInformationContainerRight()]
    },

    /**
     * creates the attribute text fields in a container
     *
     * @returns [Ext.container.Container]
     */
    createAdditionalInformationContainerLeft: function () {
        var me = this;

        var scha1_orderfield1TxtBox = Ext.create('Ext.form.TextArea', {
            name: 'scha1_orderfield1TxtBox',
            width: 380,
            labelWidth: 140,
            fieldLabel: me.snippets.additionalInformation.orderfield1Label + ' (0/255)',
            maxLength: 255,
            enforceMaxLength: true,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    field.labelEl.update(me.snippets.additionalInformation.orderfield1Label + ' ('+newValue.length+'/255)');
                    me.fireEvent('changeAttrField', field, newValue, oldValue);
                }
            }
        });

        var scha1_orderfield2TxtBox = Ext.create('Ext.form.TextArea', {
            name: 'scha1_orderfield2TxtBox',
            width: 380,
            labelWidth: 140,
            fieldLabel: me.snippets.additionalInformation.orderfield2Label + ' (0/255)',
            maxLength: 255,
            enforceMaxLength: true,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    field.labelEl.update(me.snippets.additionalInformation.orderfield2Label + ' ('+newValue.length+'/255)');
                    me.fireEvent('changeAttrField', field, newValue, oldValue);
                }
            }
        });

        var dataDropdown = Ext.create('Ext.data.Store', {
            fields : ['abbr', 'name'],
            data : [
                { "abbr": "Ja", "name": "Ja" },
                { "abbr": "Nein", "name": "Nein" }
            ]
        });

        var dataDropdownOrderType = Ext.create('Ext.data.Store', {
            fields : ['abbr', 'name'],
            data : [
                { "abbr": "Derma", "name": "Derma" },
                { "abbr": "Neuro", "name": "Neuro" },
                { "abbr": "Taurus", "name": "Taurus" }
            ]
        });

        var dataDropdownAddressGroup = Ext.create('Ext.data.Store', {
            fields : ['abbr', 'name'],
            data : [
                { "abbr": "Ärzte/Apotheken", "name": "Ärzte/Apotheken" },
                { "abbr": "Außen- u. Innendienst", "name": "Außen- u. Innendienst" },
                { "abbr": "Veranstaltung", "name": "Veranstaltung" }
            ]
        });

        var scha1_orderfield3TxtBox = Ext.create('Ext.form.field.ComboBox', {
            name: 'scha1_orderfield3TxtBox',
            queryMode: 'local',
            store: dataDropdownOrderType,
            flex: 1,
            width: 380,
            labelWidth: 140,
            listConfig: {
                maxHeight: 200
            },
            allowBlank: true,
            valueField: 'abbr',
            fieldLabel: me.snippets.additionalInformation.orderfield3Label,
            tpl: me.createDDComboTpl(0),
            displayTpl: me.createDDComboTpl(1),
            listeners: {
                'change': function (comboBox, record) {
                    me.fireEvent('changeDD', comboBox, record);
                }
            }
        });

        var scha1_orderfield4TxtBox = Ext.create('Ext.form.field.ComboBox', {
            name: 'scha1_orderfield4TxtBox',
            queryMode: 'local',
            store: dataDropdownAddressGroup,
            flex: 1,
            width: 380,
            labelWidth: 140,
            listConfig: {
                maxHeight: 200
            },
            allowBlank: true,
            valueField: 'abbr',
            fieldLabel: me.snippets.additionalInformation.orderfield4Label,
            tpl: me.createDDComboTpl(0),
            displayTpl: me.createDDComboTpl(1),
            listeners: {
                'change': function (comboBox, record) {
                    me.fireEvent('changeDD', comboBox, record);
                }
            }
        });

        var scha1_orderfield5TxtBox = Ext.create('Ext.form.TextField', {
            name: 'deliverydate_dateTxtBox',
            width: 380,
            labelWidth: 140,
            fieldLabel: me.snippets.additionalInformation.orderfield5Label,
            maxLength: 10,
            enforceMaxLength: true,
            emptyText: 'DD.MM.JJJJ',
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeAttrField', field, newValue, oldValue);
                }
            }
        });

        var attr1TxtBox = Ext.create('Ext.form.TextField', {
            name: 'attr1TxtBox',
            width: 230,
            labelWidth: 140,
            fieldLabel: me.snippets.additionalInformation.attribute1Label,
            maxLengthText: 255,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeAttrField', field, newValue, oldValue);
                }
            }
        });

        var attr2TxtBox = Ext.create('Ext.form.TextField', {
            name: 'attr2TxtBox',
            width: 230,
            labelWidth: 140,
            fieldLabel: me.snippets.additionalInformation.attribute2Label,
            maxLengthText: 255,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeAttrField', field, newValue, oldValue);
                }
            }
        });

        var attr3TxtBox = Ext.create('Ext.form.TextField', {
            name: 'attr3TxtBox',
            width: 230,
            labelWidth: 140,
            fieldLabel: me.snippets.additionalInformation.attribute3Label,
            maxLengthText: 255,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeAttrField', field, newValue, oldValue);
                }
            }
        });

        var attr4TxtBox = Ext.create('Ext.form.TextField', {
            name: 'attr4TxtBox',
            width: 230,
            labelWidth: 140,
            fieldLabel: me.snippets.additionalInformation.attribute4Label,
            maxLengthText: 255,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeAttrField', field, newValue, oldValue);
                }
            }
        });

        var attr5TxtBox = Ext.create('Ext.form.TextField', {
            name: 'attr5TxtBox',
            width: 230,
            labelWidth: 140,
            fieldLabel: me.snippets.additionalInformation.attribute5Label,
            maxLengthText: 255,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeAttrField', field, newValue, oldValue);
                }
            }
        });

        var attr6TxtBox = Ext.create('Ext.form.TextField', {
            name: 'attr6TxtBox',
            width: 230,
            labelWidth: 140,
            fieldLabel: me.snippets.additionalInformation.attribute6Label,
            maxLengthText: 255,
            listeners: {
                change: function (field, newValue, oldValue, eOpts) {
                    me.fireEvent('changeAttrField', field, newValue, oldValue);
                }
            }
        });

        var additionalInfoContainer = Ext.create('Ext.Container', {
            name: 'additionalInformationContainer',
            width: 75,
            height: 'auto',
            items: [
                scha1_orderfield1TxtBox,
                scha1_orderfield2TxtBox,
                scha1_orderfield3TxtBox,
                scha1_orderfield4TxtBox,
                scha1_orderfield5TxtBox,
                /* attr1TxtBox,
                attr2TxtBox,
                attr3TxtBox,
                attr4TxtBox,
                attr5TxtBox,
                attr6TxtBox */
            ]
        });

        return Ext.create('Ext.container.Container', {
            layout: 'hbox',
            flex: 10,
            title: 'left',
            padding: '10 0 0 10',
            autoHeight: true,
            items: [
                additionalInfoContainer
            ]
        });
    },

    createDDComboTpl: function (temp) {
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

    /**
     * @returns { Ext.XTemplate }
     */
    createLinienTemplate: function () {
        var me = this;

        var template = new Ext.XTemplate(
            '{literal}',
            '<div style="font-size: 13px;">',
            '<p><b>Linienversand</b></p>',
            '</div>',
            '{/literal}'
        );

        return template;
    },

    /**
     * creates the desktop type combobox in a extra container for the correct layout
     *
     * @returns [Ext.container.Container]
     */
    createAdditionalInformationContainerRight: function () {
        var me = this;

        var desktopType = Ext.create('Ext.form.field.ComboBox', {
            name: 'desktop-type',
            width: 220,
            queryMode: 'local',
            store: me.subApplication.getStore('DesktopTypes'),
            displayField: 'name',
            helpTitle: me.snippets.additionalInformation.desktopType,
            helpText: me.snippets.additionalInformation.desktopTypeHelpText,
            valueField: 'id',
            fieldLabel: me.snippets.additionalInformation.desktopType,
            listeners: {
                change: function (comboBox, newValue, oldValue) {
                    me.fireEvent('changeDesktopType', comboBox, newValue);
                }
            }
        });

		var customerGroup = Ext.create('Ext.form.field.ComboBox', {
            name: 'customer-group',
            width: 350,
            /* queryMode: 'local', */
            store: me.subApplication.getStore('CustomerGroup'),
            displayField: 'name',
            valueField: 'id',
            fieldLabel: me.snippets.additionalInformation.selectCustomerGroup,
            listeners: {
                change: function (comboBox, newValue, oldValue) {
                    var store = customersList.getStore();
                    customersList.enable();
                    store.removeAll();
                    newValue = '['+newValue+']';
                    newValue = JSON.parse(newValue);
                    newValueIds = [];
                    for (var ind=0; ind < newValue.length; ind++) {
                        store.insert(ind, newValue[ind]);
                        newValueIds.push(newValue[ind].id);
                    }

                    me.fireEvent('selectCustomerGroup', comboBox, newValueIds);
                }
            }
        });

        var customerStore = Ext.create('Ext.data.Store', {
            fields: ['id', 'name'],
            data: [
            ]
        });

        var customersList = Ext.create('Ext.form.ComboBox', {
            fieldLabel: 'Benutzer ausschließen',
            store: customerStore,
            width: 350,
            queryMode: 'local',
            displayField: 'name',
            valueField: 'id',
            multiSelect: true,
            disabled: true,
            listeners: {
                change: function (comboBox, newValue, oldValue) {
                    me.fireEvent('selectCustomers', comboBox, newValue);
                }
            }
        });

        var titleCustomerGroup = Ext.create('Ext.view.View', {
            id: 'LinienversandView',
            name: 'LinienversandView',
            height: 25,
            tpl: me.createLinienTemplate()
        });

        var additionalInfoContainer = Ext.create('Ext.Container', {
            name: 'additionalInformationContainer',
            width: 350,
            height: 'auto',
            items: [
                titleCustomerGroup,
                customerGroup,
                customersList,
                desktopType,
            ]
        });

        return Ext.create('Ext.container.Container', {
            layout: 'hbox',
            flex: 9,
            title: 'right',
            padding: '10 0 0 10',
            autoHeight: true,
            items: [
                additionalInfoContainer
            ]
        });
    }
});
//{/block}