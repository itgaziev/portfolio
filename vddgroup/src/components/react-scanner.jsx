import React from 'react'
import { View, Text, TouchableOpacity, Vibration, StyleSheet, Image, Animated, Platform } from 'react-native'
import { Camera, useCameraPermissions } from 'expo-camera'
import { colors, fonts, height, width } from '../themes'

const { useCode, set } = Animated

export const RNScanner = (props) => {
    const { handleBarCodeScanned, scanned, styles } = props
    const [permission, requestPermission] = useCameraPermissions();

    const animation = React.useRef(new Animated.Value(0)).current;
    const position = animation.interpolate({
        inputRange: [0, 1],
        outputRange: [10, 150],
    })
    const downPosition = () => {
        Animated.timing(animation, {
            toValue: 1,
            duration: 2000,
            useNativeDriver: false
        }).start(({finished}) => finished ? upPosition() : undefined )
    }

    const upPosition = () => {
        Animated.timing(animation, {
            toValue: 0,
            duration: 2000,
            useNativeDriver: false
        }).start(({finished}) => finished ? downPosition() : undefined )     
    }
        
    const eventFoundBarcode = (e) => {
        Vibration.vibrate(200)
        handleBarCodeScanned(e)
    }

    React.useEffect(() => {
        downPosition()
    }, [])

    if(!permission.granted) {
        return (
            <View style={ styles.notaccess}>
                <Text style={{ fontSize: 16, fontFamily: fonts.regular, color : colors.cancel }}>Нет доступа к камере</Text>
                <TouchableOpacity style={ styles.giveAccessBtn } onPress={requestPermission} >
                    <Text style={{ color: '#FFF' }}>Запросить разрешение</Text>
                </TouchableOpacity>
            </View>
        )
    }
    return (
        <View style={{ alignItems: 'center', justifyContent: 'center' }}>
            <Camera
                // barCodeTypes={Platform.OS === 'ios' ? undefined : ['ean13', 'ean8', 'code128', 'code39', 'ean128']}
                onBarCodeScanned={scanned ? undefined : eventFoundBarcode} 
                style={ [StyleSheet.absoluteFillObject, theme.camera] }             
            />
            <View style={ theme.boxScan }>
                <Image source={ require('../../assets/box-scan.png') } style={{ width: '100%', resizeMode: 'contain' }}/>
                <Animated.View style={[theme.lineBar, { top: position }]} />
            </View>
        </View>
    )
}

const theme = StyleSheet.create({
    camera : {
        position : 'absolute',
        left: 0,
        right : 0,
        height: height,
        top: 0,
        width: width,
        zIndex: 1
    },
    boxScan : {
        position: 'absolute',
        width: 271,
        height: 164,
        left: 271 * .28,
        right: 271 * .28,
        top: height * .3,
        zIndex: 2,
        resizeMode: 'contain',
        justifyContent: 'center',
        alignItems: 'center'
    },
    lineBar : {
        position: 'absolute',
        backgroundColor: 'red',
        borderRadius: 10,
        left: 10,
        right: 10,
        height: 5,
        top: 164
    }
})