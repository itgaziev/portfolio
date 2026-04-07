import { createSlice } from "@reduxjs/toolkit";

const initialState = {
    header: [],
    row: [],
    values: []
}

export const fileData = createSlice({
    name: 'file',
    initialState,
    reducers: {
        setFile: (state, action) => {
            //TODO : state modified
            state.header = action.payload.header
            state.row = action.payload.row
            state.values = action.payload.values
            console.log(state, 'values')
        },
        setType: (state, action) => {
            state.header[action.payload.id].params.type = action.payload.type
        },
        setProp: (state, action) => {
            const { id, key, propId } = action.payload
            state.header[id].params.changeProp = { key : key, id : propId }
        },
        clearProp: (state, action) => {

        },
        setCreateType: (state, action) => {
            const { id, create } = action.payload
            state.header[id].params.createType = create
        },
        setValue: (state, action) => {
            const {index, value } = action.payload
            state.values[index].new_value = value
        },
        setNewValueName: (state, action) => {
            const {index, value } = action.payload
            state.values[index].rename_value = value
        },
        addMore: (state, action) => {
            const {index} = action.payload
            state.values[index].more.push({
                value : '',
                rename_value: ''
            })
        },
        removeMore: (state, action) => {
            const { index, id } = action.payload
            state.values[index].more.splice(id, 1)
        },
        updateMore: (state, action) => {
            const { index, id, value } = action.payload
            state.values[index].more[id].value = value

        },
        updateMoreNew: (state, action) => {
            const { index, id, value } = action.payload
            state.values[index].more[id].rename_value = value
        },
        clearValues: (state, action) => {
            const { id } = action.payload

            state.values.map(item => {
                if(item.headId === id) {
                    item.more = []
                    item.new_value = ''
                    item.rename_value = ''
                }
            })
        }
    }
})

export const {
    setFile,
    setType,
    setProp,
    clearProp,
    setCreateType,
    setValue,
    setNewValueName,
    addMore,
    removeMore,
    updateMore,
    clearValues,
    updateMoreNew
} = fileData.actions

export default fileData.reducer