import React from 'react'
import { View, Text, TouchableOpacity, Vibration, StyleSheet, Image, Animated, Platform } from 'react-native'
import { CameraView, Camera } from "expo-camera";
import * as ScreenOrientation from 'expo-screen-orientation'
import { colors, fonts, height, width } from '../themes'

const { useCode, set } = Animated

const platform = {
    ios : {
        lineHeight : 150 / 1.5,
        width: 271 / 1.5,
        height: 164 / 1.5,
        top: 164 / 1.5,
        left : (271 * 1.5) * .28,
        right: (271 * 1.5) * .28,
        posTop : height * .4
    },
    android : {
        lineHeight : 150,
        width: 271,
        height: 164,
        top: 164,
        left : 271 * .28,
        right: 271 * .28,
        posTop : height * .3
    }
}

const config = Platform.OS === 'ios' ? platform.ios : platform.android


export const Scanner = (props) => {
    const { handleBarCodeScanned, scanned, styles } = props
    
    const [hasPermission, setHasPermission] = React.useState(false)
    const animation = React.useRef(new Animated.Value(0)).current;
    const position = animation.interpolate({
        inputRange: [0, 1],
        outputRange: [10, config.lineHeight],
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
    const givePermissions = () => {
        (async () => {
            const { status } = await Camera.requestCameraPermissionsAsync();
            setHasPermission(status === "granted");
        })()
    }
    
    const eventFoundBarcode = (e) => {
        Vibration.vibrate(200)
        handleBarCodeScanned(e)
    }

    const lockScreen = async() => {
        // const orient = await ScreenOrientation.getOrientationAsync()
        // console.log(orient)
        await ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.PORTRAIT_UP)
    }

    React.useEffect(() => {
        lockScreen()
        givePermissions()
        downPosition()
    }, [])
    
    if(!hasPermission) {
        return (
            <View style={ styles.notaccess}>
                <Text style={{ fontSize: 16, fontFamily: fonts.regular, color : colors.cancel }}>Нет доступа к камере</Text>
                <TouchableOpacity style={ styles.giveAccessBtn } onPress={() => givePermissions() }>
                    <Text style={{ color: '#FFF' }}>Запросить разрешение</Text>
                </TouchableOpacity>
            </View>
        )
    }
    return (
        <View style={{ alignItems: 'center', justifyContent: 'center' }}>
            <CameraView
                onBarCodeScanned={scanned ? undefined : eventFoundBarcode} 
                barcodeScannerSettings={{
                    barcodeTypes: ['ean13', 'ean8', 'code39', 'code93', 'code128'],
                }}
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
        width: config.width,
        height: config.height,
        left: config.left,
        right: config.right,
        top: config.posTop,
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
        top: config.top
    }
})