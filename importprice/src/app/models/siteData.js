import { createSlice} from "@reduxjs/toolkit"

const initialState = {
    data: []
}

export const siteData = createSlice({
    name: 'site',
    initialState,
    reducers: {
        setData: (state, action) => {
            state.data = action.payload
        }
    }
})

export const selectFields = (state) => state.site.data.filter(group => group.id === "FIELDS" || group.id === "PROPERTY")
export const selectPrice = (state) => state.site.data.filter(group => group.id === 'PRICE')
export const selectStore = (state) => state.site.data.filter(group => group.id === 'STORES')
export const selectProps = (state) => state.site.data.filter(group => group.id === 'PROPERTY')

export const { setData } = siteData.actions
export default siteData.reducer