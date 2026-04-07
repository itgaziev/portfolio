import React from 'react'
import { TouchableOpacity, View, Text, StyleSheet } from 'react-native'
import { colors, fonts } from '../themes'

export const UserItem = (props) => {
    const { item, handle } = props
    return (
        <View style={ styles.block } key={ item.id }>
            <Text style={ styles.username }>{ item.name }</Text>
            <TouchableOpacity 
                style={ [styles.btnChange, item.access == 0 ? { backgroundColor: colors.primary } : { backgroundColor: colors.cancel } ] }
                onPress={() => handle(item.id)}
            >
                <Text style={ styles.btnChangeTxt }>{ item.access == 0 ? 'Чтения' : 'Изменения' }</Text>
            </TouchableOpacity>
        </View>
    )
}

const styles = StyleSheet.create({
    block : {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        paddingHorizontal: 15,
        paddingVertical: 5,
        backgroundColor: '#FFF',
        borderRadius: 5,
        marginBottom: 5,
    },
    btnChange : {
        backgroundColor: colors.primary,
        paddingVertical: 10,
        width: 110,
        borderRadius: 2
    },
    btnChangeTxt : {
        textAlign: 'center',
        color: '#FFF',
        fontFamily: fonts.regular,
        fontSize: 14
    },
    username : {
        fontFamily: fonts.regular,
        fontSize: 16
    }
})