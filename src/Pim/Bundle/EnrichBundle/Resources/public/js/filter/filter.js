'use strict';

define([
    'underscore',
    'oro/translator',
    'oro/mediator',
    'pim/form',
    'text!pim/template/filter/filter'
], function (
    _,
    __,
    mediator,
    BaseForm,
    filterTemplate
) {
    return BaseForm.extend({
        className: 'control-group',
        elements: {},
        editable: true,
        removable: false,
        filterTemplate: _.template(filterTemplate),
        events: {
            'click .remove': 'removeFilter'
        },

        /**
         * Sets the field code on which this filter operates.
         *
         * @param {string} field
         */
        setField: function (field) {
            var data = this.getFormData();
            data.field = field;
            this.setData(data, {silent: true});
        },

        /**
         * Gets the field code on which this filter operates.
         *
         * @return {string}
         */
        getField: function () {
            return this.getFormData().field;
        },

        /**
         * Sets the current operator.
         *
         * @param {string} operator
         */
        setOperator: function (operator) {
            var data = this.getFormData();
            data.operator = operator;
            this.setData(data, {silent: true});
        },

        /**
         * Gets the current operator.
         *
         * @return {string}
         */
        getOperator: function () {
            return this.getFormData().operator;
        },

        /**
         * Sets the current value.
         *
         * @return {string}
         */
        setValue: function (value) {
            var data = this.getFormData();
            data.value = value;
            this.setData(data, {silent: true});
        },

        /**
         * Gets the current value.
         *
         * @return {string}
         */
        getValue: function () {
            return this.getFormData().value;
        },

        /**
         * Sets this filter as editable or not.
         *
         * @param {boolean} editable
         */
        setEditable: function (editable) {
            this.editable = Boolean(editable);
        },

        /**
         * Returns whether this filter is editable.
         *
         * @returns {boolean}
         */
        isEditable: function () {
            return this.editable;
        },

        /**
         * Sets this filter as removable or not.
         *
         * @param {boolean} removable
         */
        setRemovable: function (removable) {
            this.removable = removable;
        },

        /**
         * Is the filter removable?
         *
         * @return {boolean}
         */
        isRemovable: function () {
            return this.removable;
        },

        /**
         * Triggers the filter removal event.
         */
        removeFilter: function () {
            this.trigger('filter:remove', this.getField());
        },

        /**
         * Renders the filter.
         *
         * @return {Promise}
         */
        render: function () {
            var promises  = [];
            this.elements = {};
            this.setEditable(true);

            mediator.trigger('pim_enrich:form:filter:extension:add', {filter: this, promises: promises});

            $.when.apply($, promises)
                .then(this.getTemplateContext.bind(this))
                .then(function (templateContext) {
                    this.$el.html(this.filterTemplate(templateContext));

                    this.$('.filter-input').append(this.renderInput(templateContext));

                    this.renderElements();
                    this.postRender();
                    this.delegateEvents();
                }.bind(this));

            return this;
        },

        /**
         * Gets the template context.
         *
         * @returns {Promise}
         */
        getTemplateContext: function () {
            var deferred = $.Deferred();

            deferred.resolve({
                label: __('pim_enrich.export.product.filter.' + this.getField() + '.title'),
                removable: this.removable
            });

            return deferred.promise();
        },

        /**
         * Renders the input inside the filter area.
         *
         * @throws {Error} if this method is not implemented
         */
        renderInput: function () {
            throw new Error('You should implement your filter template');
        },

        /**
         * Renders extension elements of the filter.
         */
        renderElements: function () {
            _.each(this.elements, function (elements, position) {
                var $container = this.$('.' + position + '-elements-container');
                $container.empty();

                _.each(elements, function (element) {
                    if ('function' === typeof element.render) {
                        $container.append(element.render().$el);
                    } else {
                        $container.append(element);
                    }
                }.bind(this));
            }.bind(this));
        },

        /**
         * Called after rendering the input.
         */
        postRender: function () {},

        /**
         * Adds an extension element to this filter.
         *
         * @param {string} position 'label' or 'after-input'
         * @param {string} code
         * @param {Object} element
         */
        addElement: function (position, code, element) {
            if (!this.elements[position]) {
                this.elements[position] = {};
            }

            this.elements[position][code] = element;
        }
    });
});
