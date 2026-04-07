export const compileFile = ({row}) => {
    let values = []
    let newHeader = []
    let header = []
    row.map(item => {
        header = header.concat(Object.keys(item).filter(i=>header.indexOf(i)===-1));
    })
    console.log(row, header);
    row.map(item => {
        Object.keys(header).map(h => {
            let head = header[h]
            let value = item[head]

            value = item[head] === undefined ? null : value + "" //fix number value

            let search = values.filter(val => val.value === value && val.headId === h)

            if(search.length === 0) {
                values.push({
                    headId : h,
                    value : value,
                    new_value : '',
                    rename_value: '',
                    more: []
                })
            }

            search = newHeader.filter(val => val.id === h)
            if(search.length === 0) {
                newHeader.push({
                    id : h,
                    name : head,
                    params : {
                        name : head,
                        type : 'SKIP',
                        isFilter : false,
                        changeProp : '',
                        propType : '',
                        createType : ''
                    }
                })
            }
        })
    })

    return { header : newHeader, row : row, values : values}
}
export const compileData = (data, isFilter = false) => {
    let searchGroup = []

    data.map(group => {
        let options = [];
        group.options.map(item => {
            if(item.isFilter && isFilter) options.push(item)
            else if(!isFilter) options.push(item)
        } )

        if(options.length) {
            searchGroup.push({
                id: group.id,
                label: group.label,
                options: options
            })
        }
    })

    return searchGroup;
}

export const findProp = ({ propId, data }) => {
    let prop = null
    data.map(group => {
        group.options.map(item => {
            if(item.id === propId) prop = item;
        })
    })

    return prop
}

export const parseItems = (settings, filters, items) => {
    let arItems = []
    //console.log(filters)
    settings.row.map(item => {
        let arItem = {
            result : 'stand',
            header : { id : null, name : null, url : null },
            update : [],
            create : [],
            prices : [],
            stores : [],
            image : '',
            gallery : [],
            description : '',
            dbitem : {}
        }
        filters.map(filter => {
            let value = item[filter.name]
            if(typeof value === 'number') value = value.toString()
            let code = filter.params.changeProp.id !== 'ID' ? `PROPERTY_${filter.params.changeProp.id}_VALUE` : 'ID'

            let dbItem = items.filter(db => {
                return db[code] === value
            })

            if(dbItem.length) arItem.dbitem = dbItem[0]
        })

        if(arItem.dbitem.ID !== undefined) {
            settings.header.map(head => {
                let type = head.params.type
                if(type !== 'SKIP' && type !== 'FILTER') {
                    let value = item[head.name]
                    let settingValue = settings.values.filter(val => val.value === value && val.headId === head.id)
                    if(settingValue.length) value = settingValue[0]

                    switch(type) {
                        case 'CREATE':
                            arItem.create.push({
                                name : head.params.name,
                                create : head.params.createType,
                                value : value,
                                original : item[head.name]
                            })
                            break;
                        case 'UPDATE':

                            arItem.update.push({
                                name : head.params.name,
                                id : head.params.changeProp,
                                value : value,
                                original : item[head.name]
                            })
                            break;
                        case 'MAIN_IMAGE':
                            arItem.image = value
                            break;
                        case 'GALLERY':
                            arItem.gallery.push(value)
                            break;
                        case 'DESCRIPTION':
                            arItem.description = value
                            break;
                        case 'PRICE':
                            arItem.prices.push({
                                name : head.params.name,
                                id : head.params.changeProp,
                                value : value,
                                original : item[head.name]
                            })
                            break;
                        case 'STORE':
                            arItem.stores.push({
                                name : head.params.name,
                                id : head.params.changeProp,
                                value : value,
                                original : item[head.name]
                            })
                            break;
                    }
                }
            })

            arItem.header.id = arItem.dbitem.ID
            arItem.header.name = arItem.dbitem.NAME
            arItem.header.url = arItem.dbitem.DETAIL_PAGE_URL
            arItems.push(arItem)
        }
    })

    return arItems
}