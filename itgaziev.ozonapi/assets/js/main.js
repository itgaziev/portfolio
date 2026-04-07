BX.ready(function(){
    BX.bind(BX('itgaziev_ozonapi_check_btn'), 'click', () => check_connection_ozonapi());
});

function check_connection_ozonapi() {
    BX.adjust(BX('check_connection_ozonapi'), { text: 'Идет проверка ...' });

    let settings_id = $('input[name="ID"]').val();
    BX.ajax({   
        url: '/bitrix/admin/itgaziev.ozonapi_ajax.php',
        data: {
            route : 'test/connection',
            params : {settings_id : settings_id }
        },
        method: 'POST',
        dataType: 'json',
        timeout: 3600,
        async: true,
        processData: true,
        scriptsRunFirst: true,
        emulateOnload: true,
        start: true,
        cache: false,
        onsuccess: function(data){
            console.log(data)
            if(data.status === 200) {
                BX.adjust(BX('check_connection_ozonapi'), { html: `<span class="alert-success-result">Соединения установлено</span>` });
            } else {
                BX.adjust(BX('check_connection_ozonapi'), { html: `<span class="alert-error-result">Ошибка соединения [${data.status}]</span>` });
            }
        },
        onfailure: function(){

        }
    });
}
$.fn.scrollDivToElement = function(childSel) {
    if (! this.length) return this;

    return this.each(function() {
        let parentEl = $(this);
        let childEl = parentEl.find(childSel);

        if (childEl.length > 0) {
            parentEl.scrollTop(
                parentEl.scrollTop() - parentEl.offset().top + childEl.offset().top - (parentEl.outerHeight() / 2) + (childEl.outerHeight() / 2)
            );
        }
    });
};

var ozonapiImportJS = function(args) {
    this.default = {
        iblock : 0,
        counter : 1,
        currentTab : 'edit1',
        section: { 
            search : [], 
            result : [], 
            change : [], 
            selector : '#ozonapi-select-category-body' 
        },
        select2: { selected : null, optional : [], attribute : {} },
        selector: {
            section: '#ozonapi-select-category-body',
            sectionInput : '#ozonapi-search-section',
        },
        filter: {
            condition: [],
            rule: [],
        }
    }    
    this.options = Object.assign(this.default, args);

    this.setFilter = (filter) => this.options.filter = filter;

    this.setChange = (change) => this.options.section.change = change;
    
    this.getChange = () => this.options.section.change;

    this.getCounter = () => console.log(this.options.section.search);

    /* TAB CONTROL EDIT1 SECTION CONTROL */
    this.initSectionTab = () => {
        let search = this.options.section.search;
        let _this = this;
        $( "#ozonapi-search-section").autocomplete({
            source: search.tags, 
            autoFocus: true,
            minLength: 3,
            maxHeight: 200
        });
    
        $("#ozonapi-search-section").on( "autocompleteclose", function( event, ui ) {
            let select = $(this).val();
            let result = search.options.filter(item => item.name === select);
            if(result[0] !== undefined) {
                _this.setChange(result[0].id);
                _this.buildSection();
                _this.updateSectionInput();
            }
        });   
        
        this.buildSection();
    }

    this.buildTemp = () => {
        let temp = `
            <div class="ozonapi-select-category__column" data-id="level-1"><ul></ul></div>
            <div class="ozonapi-select-category__column" data-id="level-2"><ul></ul></div>
            <div class="ozonapi-select-category__column" data-id="level-3"><ul></ul></div>
        `;
        this.removeHandler('li.ozonapi-change-category', this.setSectionEvent);

        $(this.options.selector.section).html($(temp));

    }

    this.buildSection = (data = false, curLevel = 0, ids = []) => {
        let section = this.options.section;
        let _this = this;
        data = data !== false ? data : (section.result.result !== undefined) ? section.result.result : [];
        if(curLevel === 0) this.buildTemp();

        data.map(item => {
            let _ids = ids;

            let isActive = section.change && section.change[curLevel] == item.category_id;
            let isMain = curLevel === 0;
            if(isMain) _ids = [];
            _ids[curLevel] = item;

            if(isActive) {                              
                if(curLevel !== 2) _this.buildSection(item.children, curLevel+1, _ids);
            }
            _this.buildTemplate(item, curLevel, isActive, _ids);
        });
        if(curLevel === 0) {
            this.buildName();
            this.addHandler('li.ozonapi-change-category', this.setSectionEvent);
            section.change.map((item, index) => {
                let level = index + 1;
                $(this.options.selector.section).find('.ozonapi-select-category__column[data-id="level-'+level+'"]').scrollDivToElement("#ozon-section-" + item)
            });
        }

    }

    this.buildTemplate = (item, level, isActive, ids) => {
        let addClass = isActive ? 'active' : '';
        let append = (level !== 2) ? '<span class="adm-submenu-item-arrow-icon"></span>' : '';
        let dataParam = '';
        ids.map((value, index) => { if(index <= level) dataParam += `data-level-${index+1}="${value.category_id}" `; })

        let temp = `<li class="ozonapi-change-category ${addClass}" data-level="${level+1}" id="ozon-section-${item.category_id}" data-id="${item.category_id}" ${dataParam}>${item.title} ${append}</li>`;
        $(this.options.selector.section).find('.ozonapi-select-category__column[data-id="level-'+(level + 1)+'"] > ul').append(temp);
    }

    this.buildName = () => {
        let name = '';
        let section = this.options.section;
        let data = (section.result.result !== undefined) ? section.result.result : [];
        data.map(item => {
            if(section.change && section.change[0] == item.category_id) {
                name = item.title;
                item.children.map(item2 => {
                    if(section.change && section.change[1] == item2.category_id) {
                        name = item.title + '/' + item2.title;
                        item2.children.map(item3 => {
                            if(section.change && section.change[2] == item3.category_id) {
                                name = item.title + '/' + item2.title + '/' + item3.title;
                            }
                        });
                    }
                });
            }
        });
    
        $(this.options.selector.sectionInput).val(name);
    }

    this.updateSectionInput = () => {
        $('input[name="OZON_SECTION_1"]').val("");
        $('input[name="OZON_SECTION_2"]').val("");
        $('input[name="OZON_SECTION_3"]').val("");
        this.options.section.change.map((id, i) => $('input[name="OZON_SECTION_'+(i + 1)+'"]').val(id))
    }
    /******************************************************************/

    /********************* OZONAPI ATTRIBUTES TAB *********************/
    this.initAttributeTab = () => {
        $('.js-ozonapi-table__select[data-type="price"]').select2({ data : this.options.select2.attribute.price, width: 'style', placeholder : 'Выберите значение' });
        $('.js-ozonapi-table__select[data-type="unit"]').select2({ data : this.options.select2.attribute.unit, width: 'style', placeholder : 'Выберите значение' });
        $('.js-ozonapi-table__select[data-type="vat"]').select2({ data : this.options.select2.attribute.vat, width: 'style', placeholder : 'Выберите значение' });
        $('.js-ozonapi-table__select[data-type="dimension"]').select2({ data : this.options.select2.attribute.dimension, width: 'style', placeholder : 'Выберите значение' });
        $('.js-ozonapi-table__select[data-type="attribute"]').select2({ data : this.options.select2.attribute.attribute, width: 'style', placeholder : 'Выберите значение' });
        $('.js-ozonapi-table__select[data-type="optional"]').select2({ data : this.options.select2.attribute.optional, width: 'style', placeholder : 'Выберите значение' });
        $('.js-ozonapi-table__select[data-type="optional"]').on('select2:select', this.select2EventOptional);

        this.addHandler('.ozonapi-settings-add__attribute', this.select2AddAttribute);
        this.addHandler('.ozonapi-settings-add__attribute_remove', this.select2RemoveAttribute);
        this.addHandler('span.ozonapi-show-modal', this.showModalAttribute);
        this.addHandler('.ozonapi-modal-close', this.closeModal);
    }

    this.addSelectedTemplate = (selected) => {
        this.removeHandler('.ozonapi-settings-add__attribute_remove', this.select2RemoveAttribute);
        this.removeHandler('span.ozonapi-show-modal', this.showModalAttribute);
        this.removeHandler('.ozonapi-modal-close', this.closeModal);

        let template = `
        <div class="ozonapi-section-attribute__tr" data-id="${selected.item.id}">
            <div class="ozonapi-table__col-1"><span class="ozonapi-show-modal" data-id="${selected.item.id}">?</span> ${selected.item.name}:</div>
            <div class="ozonapi-table__col-2">
                <div class="ozonapi-table__values">
                    <select class="js-ozonapi-table__select" data-type="attribute" name="optional[${selected.item.id}][select]" data-selected="" style="width: 48%"><option></option></select>
                    <input type="text" class="ozonapi-table__input" name="optional[${selected.item.id}][input]" placeholder="Свое значение ..." style="margin: 0 10px;"/>
                    <button type="button" class="adm-btn ozonapi-settings-add__attribute_remove" data-id="${selected.item.id}">-</button>
                </div>
            </div>
            <div class="ozonapi-modal-description" data-id="${selected.item.id}">
                <div class="ozonapi-modal-wrapper">
                    <span class="ozonapi-modal-close">x</span>
                    ${selected.item.description}
                </div>
            </div>
        </div>`;

        $('#ozonapi-section-attribute__aBitrix').append(template);
        $('.js-ozonapi-table__select[data-type="attribute"]').select2({data: this.options.select2.attribute.attribute, width: 'style', placeholder : 'Выберите значение'});

        this.addHandler('.ozonapi-settings-add__attribute_remove', this.select2RemoveAttribute);
        this.addHandler('span.ozonapi-show-modal', this.showModalAttribute);
        this.addHandler('.ozonapi-modal-close', this.closeModal);
    }
    /******************************************************************/

    /******************** OZONAPI FILTER PRODUCT **********************/
    this.initFilterTab = () => {
        $(document).on('click', '#ozonapi-add-group-rule', this.filterAddRuleGroup);
    }

    this.loadSavedFilters = (saved) => {
        saved.map(item => {
            let guid = item.group_id;
            let template = `
            <div class="ozonapi-rule-group" id="${guid}">
                <div class="ozonapi-rule-group__body">
                </div>
                <div class="ozonapi-rule-group__footer">
                    <button type="button" class="adm-btn condtion-add-rule" data-guid="${guid}">Добавить условие</button>
                    <button type="button" class="adm-btn condition-remove-group-rule" data-guid="${guid}">Удалить группу</button>
                </div>
            </div>        
            `;

            $('#ozonapi-rule-import > .ozonapi-rule-groups').append(template);
            $('button.condtion-add-rule[data-guid="'+guid+'"]').on('click', this.filterAddRule);
            $('button.condition-remove-group-rule[data-guid="'+guid+'"]').on('click', this.filterRemoveGroupRule);
    
            item.rule.map((rule, index) => {
                let id = index; 

                let val = ``;
                let vOption = '';
                switch(rule.compare.id) {
                    case 'bool-equal': 
                        val = `<select name="condition[${guid}][${id}][value]" class="ozonapi-condition-value" data-type="${rule.condition.controll}"><option value="${rule.value.id}" selected="selected">${rule.value.text}</option></select>`;
                        break;
                    case 'bool-not-equal': 
                        val = `<select name="condition[${guid}][${id}][value]" class="ozonapi-condition-value" data-type="${rule.condition.controll}"><option value="${rule.value.id}" selected="selected">${rule.value.text}</option></select>`;
                        break;
                    case 'in': 
                        vOption = '';
                        console.log(rule);
                        if(rule.value !== null) rule.value.map(opt => { vOption = vOption + '<option value="'+ opt.id +'" selected="selected">'+ opt.text +'</option>'})
                        val = `<select name="condition[${guid}][${id}][value][]" class="ozonapi-condition-value" data-type="${rule.condition.controll}" multiple>${vOption}</selected>`;
                        break;
                    case 'not-in': 
                        vOption = '';
                        rule.value.map(opt => { vOption = vOption + '<option value="'+ opt.id +'" selected="selected">'+ opt.text +'</option>'})
                        val = `<select name="condition[${guid}][${id}][value][]" class="ozonapi-condition-value" data-type="${rule.condition.controll}" multiple>${vOption}</selected>`;
                        break;
                    case 'mask': 
                        val = `<input type="text" name="condition[${guid}][${id}][value]" value="${rule.value}" class="ozonapi-condition-value" data-type="${rule.condition.controll}" />`;
                        break;
                    case 'equals': 
                        val = `<input type="text" name="condition[${guid}][${id}][value]" value="${rule.value}" class="ozonapi-condition-value" data-type="${rule.condition.controll}" />`;
                        break;
                    case 'not-equals':
                        val = `<input type="text" name="condition[${guid}][${id}][value]" value="${rule.value}" class="ozonapi-condition-value" data-type="${rule.condition.controll}" />`;
                        break;
                    case 'over':
                        val = `<input type="text" name="condition[${guid}][${id}][value]" value="${rule.value}" class="ozonapi-condition-value" data-type="${rule.condition.controll}" />`;            
                        break;
                    case 'less':
                        val = `<input type="text" name="condition[${guid}][${id}][value]" value="${rule.value}" class="ozonapi-condition-value" data-type="${rule.condition.controll}" />`;
                        break;
                }

                let temp = `
                <div class="ozonapi-rule-condition" data-id="${id}" data-group="${guid}">
                    <div class="ozonapi-condition-column ozonapi-rule-condition__type">
                        <select name="condition[${guid}][${id}][type]" class="ozonapi-condition-type" style="width: 220px;" data-selected="${rule.type}">
                        </select>
                    </div>
                    <div class="ozonapi-condition-column ozonapi-rule-condition__compare" id="condition-compare-${guid}-${id}">
                        <select name="condition[${guid}][${id}][compare]" class="ozonapi-condition-compare" style="width: 220px;">
                            <option value="${rule.compare.id}" selected="selected">${rule.compare.text}</option>
                        </select>
                    </div>
                    <div class="ozonapi-condition-column ozonapi-rule-condition__value" id="condition-value-${guid}-${id}">
                        ${val}
                    </div>
                    <div class="ozonapi-condition-column ozonapi-rule-condition__remove">
                        <button type="button" class="adm-btn condition-remove-rule" data-guid="${guid}" data-id="${id}">Удалить условие</button>
                    </div>
                </div>                
                `;
                $('#' + guid + ' > .ozonapi-rule-group__body').append(temp);
                $('select.ozonapi-condition-type[name="condition['+guid+']['+id+'][type]"]').select2({data: this.options.filter.condition, width: 'style'});
                $('select.ozonapi-condition-type[name="condition['+guid+']['+id+'][type]"]').on('select2:select', this.selectConditionChange);
                $('select.ozonapi-condition-compare[name="condition['+guid+']['+id+'][compare]"]').select2({data: rule.condition.compare, width: 'style'});
                $('select.ozonapi-condition-compare[name="condition['+guid+']['+id+'][compare]"]').on('select2:select', this.selectConditionCompare);
                $('button.condition-remove-rule[data-guid="'+guid+'"][data-id="'+id+'"]').on('click', this.filterRemoveRule);

                if(rule.condition.controll == 'product-search') {
                    this.ajaxSelect2Product(guid, id);
                } else if(rule.condition.controll == 'product-section') {
                    this.select2Section(guid, id);
                } else if(rule.condition.type == 'list' && rule.condition.data.result) {
                    if(rule.compare.id == 'in' || rule.compare.id == 'not-in') {
                        $('select.ozonapi-condition-value[name="condition['+guid+']['+id+'][value][]"]').select2({ data : rule.condition.data.result});
                    } else {
                        $('select.ozonapi-condition-value[name="condition['+guid+']['+id+'][value]"]').select2({ data : rule.condition.data.result});
                    }
                }
            });

        });
    }

    this.getTemplateRuleGroup = (guid, id = 0) => {
        let template = `
        <div class="ozonapi-rule-group" id="${guid}">
            <div class="ozonapi-rule-group__body">
                <div class="ozonapi-rule-condition" data-id="${id}" data-group="${guid}">
                    <div class="ozonapi-condition-column ozonapi-rule-condition__type">
                        <select name="condition[${guid}][${id}][type]" class="ozonapi-condition-type" style="width: 220px;"></select>
                    </div>
                    <div class="ozonapi-condition-column ozonapi-rule-condition__compare" id="condition-compare-${guid}-${id}">
                        <select name="condition[${guid}][${id}][compare]" class="ozonapi-condition-compare" style="width: 220px;"></select>
                    </div>
                    <div class="ozonapi-condition-column ozonapi-rule-condition__value" id="condition-value-${guid}-${id}">
                        <input name="condition[${guid}][${id}][value]" type="text" class="ozonapi-condition-value" />
                    </div>
                    <div class="ozonapi-condition-column ozonapi-rule-condition__remove">
                        <button type="button" class="adm-btn condition-remove-rule" data-guid="${guid}" data-id="${id}">Удалить условие</button>
                    </div>
                </div>
            </div>
            <div class="ozonapi-rule-group__footer">
                <button type="button" class="adm-btn condtion-add-rule" data-guid="${guid}">Добавить условие</button>
                <button type="button" class="adm-btn condition-remove-group-rule" data-guid="${guid}">Удалить группу</button>
            </div>
        </div>        
        `;
        //this.getCompareTemp(compare, guid, id, this.options.filter.condition[0].children[0].condition);
        return template;
    }

    this.getCompareList = (id, group) => {
        let condition = this.options.filter.condition;

        let groupFilter = condition.filter(item => item.id == group);
        if(groupFilter.length) {
            console.log(groupFilter);
        }
    }

    this.getCompareTemp = (compare, guid, id, condition) => {
        let template = "";
        switch(compare.id) {
            case 'bool-equal': 
                template = `<select name="condition[${guid}][${id}][value]" class="ozonapi-condition-value" data-type="${condition.controll}" />`;
                break;
            case 'bool-not-equal': 
                template = `<select name="condition[${guid}][${id}][value]" class="ozonapi-condition-value" data-type="${condition.controll}" />`;
                break;
            case 'in': 
                template = `<select name="condition[${guid}][${id}][value][]" class="ozonapi-condition-value" data-type="${condition.controll}" multiple/>`;
                break;
            case 'not-in': 
                template = `<select name="condition[${guid}][${id}][value][]" class="ozonapi-condition-value" data-type="${condition.controll}" multiple/>`;
                break;
            case 'mask': 
                template = `<input type="text" name="condition[${guid}][${id}][value]" class="ozonapi-condition-value" data-type="${condition.controll}" />`;
                break;
            case 'equals': 
                template = `<input type="text" name="condition[${guid}][${id}][value]" class="ozonapi-condition-value" data-type="${condition.controll}" />`;
                break;
            case 'not-equals':
                template = `<input type="text" name="condition[${guid}][${id}][value]" class="ozonapi-condition-value" data-type="${condition.controll}" />`;
                break;
            case 'over':
                template = `<input type="text" name="condition[${guid}][${id}][value]" class="ozonapi-condition-value" data-type="${condition.controll}" />`;            
                break;
            case 'less':
                template = `<input type="text" name="condition[${guid}][${id}][value]" class="ozonapi-condition-value" data-type="${condition.controll}" />`;
                break;
        }

        $('#condition-value-'+guid+'-'+id).html(template);
        if(condition.controll == 'product-search') {
            this.ajaxSelect2Product(guid, id);
        } else if(condition.controll == 'product-section') {
            this.select2Section(guid, id);
        } else if(condition.type == 'list' && condition.data.result) {
            if(compare.id == 'in' || compare.id == 'not-in') {
                $('select.ozonapi-condition-value[name="condition['+guid+']['+id+'][value][]"]').select2({ data : condition.data.result});
            } else {
                $('select.ozonapi-condition-value[name="condition['+guid+']['+id+'][value]"]').select2({ data : condition.data.result});
            }
        }
        return template;
    }

    this.ajaxSelect2Product = (guid, id) => {
        let options = this.options;
        $('select.ozonapi-condition-value[name="condition['+guid+']['+id+'][value][]"]').select2({
            ajax: {
                transport: function (params, success, failure) {
                    let page = params.page === undefined ? 1 : params.page;
                    var $request = BX.ajax({   
                        url: '/bitrix/admin/itgaziev.ozonapi_ajax.php',
                        data: { route: 'ajax/ozonbitrix', params : { action: 'searchProduct', q: params.data.term, page: page, iblock : options.iblock } },
                        method: 'POST',
                        dataType: 'json',
                        timeout: 3600,
                        async: true,
                        processData: true,
                        scriptsRunFirst: true,
                        emulateOnload: true,
                        start: true,
                        cache: true,
                        onsuccess: success,
                        onfailure: () => {}
                    });
                    return $request;
                },
    
                processResults: this.processResults,
                cache: false
            },
            placeholder: 'Поиск товара',
            minimumInputLength: 1,
            templateResult: this.templateResult,
            templateSelection: this.templateSelection
        });
    }

    this.select2Section = (guid, id) => {
        let options = this.options;
        $('select.ozonapi-condition-value[name="condition['+guid+']['+id+'][value][]"]').select2({
            ajax: {
                transport: function (params, success, failure) {
                    let page = params.page === undefined ? 1 : params.page;
                    var $request = BX.ajax({   
                        url: '/bitrix/admin/itgaziev.ozonapi_ajax.php',
                        data: { route: 'ajax/ozonbitrix', params : { action: 'searchSection', q: params.data.term, page: page, iblock : options.iblock } },
                        method: 'POST',
                        dataType: 'json',
                        timeout: 3600,
                        async: true,
                        processData: true,
                        scriptsRunFirst: true,
                        emulateOnload: true,
                        start: true,
                        cache: true,
                        onsuccess: success,
                        onfailure: () => {}
                    });
                    return $request;
                },
    
                processResults: this.processResults,
                cache: false
            },
            placeholder: 'Поиск раздела',
            minimumInputLength: 1,
            templateResult: this.templateResult,
            templateSelection: this.templateSelection
        });
    }

    this.processResults = (data, params) => {
        console.log(data);
        params.page = params.page || 1;
        if(data.body !== undefined && data.body !== null ) {
            return { 
                results: data.body.result,
                pagination: {
                    more: (params.page * 30) < data.body.total_count
                }
            };
        } else {
            return { results: [] };
        }
    }

    this.templateResult = (repo) => {
        let container = `
        <div class='select2-result-repository clearfix'>
            <div class='select2-result-repository__meta'>
                <div class='select2-result-repository__title'>${repo.name}</div>
            </div>
        </div>`; 

        return $(container);        
    }

    this.templateSelection = (repo) => { 
        return repo.name;
    }
    /******************************************************************/

    /* EVENT BUTTONS */
    this.setSectionEvent = (e) => {
        let _this = e.target;

        let level = parseInt(e.target.dataset.level);
        if(!$(_this).hasClass('active')) {
            $(_this).parent().find('li.ozonapi-change-category').removeClass('active');
            $(_this).addClass('active');
            if(level === 1) this.options.section.change = [$(_this).attr('data-level-1')];
            else if(level === 2) this.options.section.change = [$(_this).attr('data-level-1'), $(_this).attr('data-level-2')];
            else if(level === 3) this.options.section.change = [$(_this).attr('data-level-1'), $(_this).attr('data-level-2'), $(_this).attr('data-level-3')];

            this.buildSection();
            this.updateSectionInput();
        }
    }

    this.select2EventOptional = (e) => {
        let data = e.params.data;
        let id = parseInt(data.id);
        let items = this.options.select2.optional.filter(item => item.id === id);
        this.options.select2.selected = {element : data, item : items[0]};
    }

    this.select2AddAttribute = (e) => {
        let select2 = this.options.select2;
        let selected = select2.selected;
        if( selected === null || selected === undefined ) return;
        if($('.ozonapi-section-attribute__tr[data-id="' + selected.item.id + '"]').length) return;
        
        this.addSelectedTemplate(selected);
    }

    this.select2RemoveAttribute = (e) => {
        $('.ozonapi-section-attribute__tr[data-id="'+e.target.dataset.id+'"]').remove();
    }

    this.showModalAttribute = (e) => {
        $('.ozonapi-modal-description').removeClass('active');
        $('.ozonapi-modal-description[data-id="' + e.target.dataset.id + '"]').addClass('active');
    }

    this.closeModal = (e) => {
        $('.ozonapi-modal-description').removeClass('active');
    }

    this.counter = 1;
    this.filterAddRuleGroup = (e) => {
        let guid = 'rule-' + this.counter;
        let temp = this.getTemplateRuleGroup(guid, 0);
        $('#ozonapi-rule-import > .ozonapi-rule-groups').append(temp);

        let compare = this.options.filter.condition[0].children[0].condition.compare;

        $('select.ozonapi-condition-compare[name="condition['+guid+'][0][compare]"]').select2({data: compare, width: 'style'});
        $('select.ozonapi-condition-compare[name="condition['+guid+'][0][compare]"]').on('select2:select', this.selectConditionCompare);
        $('select.ozonapi-condition-type[name="condition['+guid+'][0][type]"]').select2({data: this.options.filter.condition, width: 'style'});
        $('select.ozonapi-condition-type[name="condition['+guid+'][0][type]"]').on('select2:select', this.selectConditionChange);
        this.getCompareTemp(compare[0], guid, 0, this.options.filter.condition[0].children[0].condition);
        
        $('button.condtion-add-rule[data-guid="'+guid+'"]').on('click', this.filterAddRule);
        $('button.condition-remove-group-rule[data-guid="'+guid+'"]').on('click', this.filterRemoveGroupRule);
        $('button.condition-remove-rule[data-guid="'+guid+'"][data-id="0"]').on('click', this.filterRemoveRule);
        this.counter++;
    }
    this.filterRemoveGroupRule = (e) => {
        let id = e.target.dataset.guid;
        $('#' + id).remove();
    }

    this.filterRemoveRule = (e) => {
        let id = e.target.dataset.guid;
        let parent = $(e.target).closest('.ozonapi-rule-condition');
        $(parent).remove();
    }

    this.filterAddRule = (e) => {
        let guid = $(e.target).attr('data-guid');
        let lastID = $('#' + guid + ' .ozonapi-rule-condition:last-child').attr('data-id');
        let id = 0;
        if(lastID !== undefined) id = parseInt(lastID) + 1;

        let template = `
            <div class="ozonapi-rule-condition" data-id="${id}" data-group="${guid}">
                <div class="ozonapi-condition-column ozonapi-rule-condition__type">
                    <select name="condition[${guid}][${id}][type]" class="ozonapi-condition-type" style="width: 220px;"></select>
                </div>
                <div class="ozonapi-condition-column ozonapi-rule-condition__compare" id="condition-compare-${guid}-${id}">
                    <select name="condition[${guid}][${id}][compare]" class="ozonapi-condition-compare" style="width: 220px;"></select>
                </div>
                <div class="ozonapi-condition-column ozonapi-rule-condition__value" id="condition-value-${guid}-${id}">
                    <input name="condition[${guid}][${id}][value]" type="text" class="ozonapi-condition-value" />
                </div>
                <div class="ozonapi-condition-column ozonapi-rule-condition__remove">
                    <button type="button" class="adm-btn condition-remove-rule" data-guid="${guid}" data-id="${id}">Удалить условие</button>
                </div>
            </div>
        `;

        $('#' + guid + ' .ozonapi-rule-group__body').append(template);
        let compare = this.options.filter.condition[0].children[0].condition.compare;

        $('select.ozonapi-condition-compare[name="condition['+guid+']['+id+'][compare]"]').select2({data: compare, width: 'style'});
        $('select.ozonapi-condition-type[name="condition['+guid+']['+id+'][type]"]').select2({data: this.options.filter.condition, width: 'style'});
        $('select.ozonapi-condition-type[name="condition['+guid+']['+id+'][type]"]').on('select2:select', this.selectConditionChange);
        $('select.ozonapi-condition-compare[name="condition['+guid+']['+id+'][compare]"]').on('select2:select', this.selectConditionCompare);
        this.getCompareTemp(compare[0], guid, id, this.options.filter.condition[0].children[0].condition);
        $('button.condition-remove-rule[data-guid="'+guid+'"][data-id="'+id+'"]').on('click', this.filterRemoveRule);

    }

    this.selectConditionChange = (e) => {
        let id = $(e.target).closest('.ozonapi-rule-condition').attr('data-id');
        let guid = $(e.target).closest('.ozonapi-rule-condition').attr('data-group');
        let data = e.params.data;
        let selected = data.condition.compare[0];

        let temp = `<select name="condition[${guid}][${id}][compare]" class="ozonapi-condition-compare" style="width: 220px;"></select>`;
        $('#condition-compare-'+guid+'-'+id).html(temp);
        $('select.ozonapi-condition-compare[name="condition['+guid+']['+id+'][compare]"]').select2({data: data.condition.compare, width: 'style'});
        this.getCompareTemp(selected, guid, id, data.condition);
    }

    this.selectConditionCompare = (e) => {
        let elem = $(e.target);
        let id = $(elem).closest('.ozonapi-rule-condition').attr('data-id');
        let guid = $(elem).closest('.ozonapi-rule-condition').attr('data-group');
        let data = $('select.ozonapi-condition-type[name="condition['+guid+']['+id+'][type]"]').select2('data');
        console.log(data)
        let params = data[0];
        this.getCompareTemp(e.params.data, guid, id, params.condition);
    }

    this.addHandler = (classname, func, event = 'click') => {
        let elements = document.querySelectorAll(classname);
        Array.from(elements).map((element) => {
            element.addEventListener(event, func);
        });
    }

    this.removeHandler = (classname, func, event = 'click') => {
        let elements = document.querySelectorAll(classname);
        Array.from(elements).map((element) => {
            element.removeEventListener(event, func);
        }); 
    }
}

var startImportJS = function(args) {
    this.default = {
        timeout : 3600,
        ajaxURL : '',
        action : '',
    }
    this.options = args;
    this.step = 0;
    this.percent = 0;
    this.ajaxUrl = '';
    this.action = '';
    this.id = args.id;
    this.page = 1;
    this.runImport = () => {
        console.log(this.options);
        this.percent = 0;
        this.create();
        this.page = 1;


        $('.progress-info > .progress-type').html("Добавления в таблицу новых товаров");
    }

    this.create = () => {
        this.percent++;
        let id = this.id;
        let page = this.page;
        let _this = this;
        BX.ajax({   
            url: '/bitrix/admin/itgaziev.ozonapi_ajax.php',
            data: {
                route : 'export/create',
                params : {
                    id : id,
                    counter: 1000
                }
            },
            method: 'POST',
            dataType: 'json',
            timeout: 3600,
            async: true,
            processData: true,
            scriptsRunFirst: true,
            emulateOnload: true,
            start: true,
            cache: false,
            onsuccess: function(data){
                //console.log(data);
                if(data.body.percent !== undefined) $('#ozonapi-export-progress-bar').val(data.body.percent);
                if(data.body.finish !== 'Y') {
                    setTimeout(() => {
                        _this.create();
                    }, 1000);
                } else if(data.body.finish === 'Y') {
                    $('#ozonapi-export-progress-bar').val(0);
                    $('.progress-info > .progress-type').html("Отправка в Ozon новые товары");
                    _this.import();
                }
            },
            onfailure: function(){
    
            }
        });
    }

    this.import =  () => {
        this.percent++;
        let id = this.id;
        let page = this.page;
        let _this = this;
        BX.ajax({   
            url: '/bitrix/admin/itgaziev.ozonapi_ajax.php',
            data: {
                route : 'export/import',
                params : {
                    id : id,
                    page: 0
                }
            },
            method: 'POST',
            dataType: 'json',
            timeout: 3600,
            async: true,
            processData: true,
            scriptsRunFirst: true,
            emulateOnload: true,
            start: true,
            cache: false,
            onsuccess: function(data){
                console.log(data);
                if(data.body.percent !== undefined) $('#ozonapi-export-progress-bar').val(data.body.percent);
                if(data.body.finish !== 'Y') {
                    //_this.import();
                    setTimeout(() => {
                        _this.import();
                    }, 1000);
                } else if(data.body.finish === 'Y') {
                    $('#ozonapi-export-progress-bar').val(0);
                    $('.progress-info > .progress-type').html("Установка статусов");
                    //_this.status();
                }
            },
            onfailure: function(){
    
            }
        });        
    }

    this.status = () => {
        this.percent++;
        let id = this.id;
        let page = this.page;
        let _this = this;
        BX.ajax({   
            url: '/bitrix/admin/itgaziev.ozonapi_ajax.php',
            data: {
                route : 'export/status',
                params : { task_id : 0 }
            },
            method: 'POST',
            dataType: 'json',
            timeout: 3600,
            async: true,
            processData: true,
            scriptsRunFirst: true,
            emulateOnload: true,
            start: true,
            cache: false,
            onsuccess: function(data){
                console.log(data);
                _this.info();
            },
            onfailure: function(){
    
            }
        });        
    }

    this.info = () => {
        this.percent++;
        let id = this.id;
        let page = this.page;
        let _this = this;
        BX.ajax({   
            url: '/bitrix/admin/itgaziev.ozonapi_ajax.php',
            data: {
                route : 'export/info',
                params : { offer_id : 0 }
            },
            method: 'POST',
            dataType: 'json',
            timeout: 3600,
            async: true,
            processData: true,
            scriptsRunFirst: true,
            emulateOnload: true,
            start: true,
            cache: false,
            onsuccess: function(data){
                console.log(data);

            },
            onfailure: function(){
    
            }
        });          
    }
}