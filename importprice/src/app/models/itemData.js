import { createSlice } from "@reduxjs/toolkit";

const initialState = {
    items : []
}

export const itemData = createSlice({
    name : 'items',
    initialState,
    reducers: {
        setItems: (state, action) => {
            state.items = action.payload
        },
        updateItems : (state, action) => {

        }
    }
})

export const {
    setItems,
    updateItems
} = itemData.actions

export default itemData.reducer