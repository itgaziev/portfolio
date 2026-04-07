import { createSlice } from "@reduxjs/toolkit"

const initialState = {
    token : null
}

export const userData = createSlice({
    name: 'user',
    initialState,
    reducers: {
        setToken: (state, action) => {
            //console.log(action)
            state.token = action.payload
        }
    }
})
export const userToken = state => state.token === null ? state.token : localStorage.getItem('token')
export const { setToken } = userData.actions

export default userData.reducer