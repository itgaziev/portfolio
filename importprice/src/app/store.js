import { configureStore } from "@reduxjs/toolkit"
import fileReducer from './models/fileData'
import siteReducer from './models/siteData'
import userReducer from './models/userData'
import itemReducer from "./models/itemData";
import doubleReducer from "./models/doubleItems";

export const store = configureStore({
    reducer: {
        user : userReducer,
        site : siteReducer,
        file : fileReducer,
        items : itemReducer,
        double: doubleReducer
    }
})