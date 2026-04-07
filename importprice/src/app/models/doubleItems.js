import { createSlice } from "@reduxjs/toolkit";

const initialState = {
    double : []
}

export const itemData = createSlice({
    name : 'double',
    initialState,
    reducers: {
        addDouble: (state, action) => {
            state.double.push({ prop : action.payload.prop, id : action.payload.id, items : action.payload.items, total : action.payload.total, compare : action.payload.compare })
        },
        updateDouble : (state, action) => {
            state.double.map(d => {
                if(d.prop === action.payload.prop) {
                    d.items.map(item => {
                        if(item.sku === action.payload.sku) {
                            item.currentItem = action.payload.selected
                        }
                    })
                }
            })
            
        },
        cleareDouble : (state, action) => {

        }
    }
})

export const {
    addDouble,
    updateDouble
} = itemData.actions


export default itemData.reducer