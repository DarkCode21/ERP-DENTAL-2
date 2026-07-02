/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 * @author Miguel Bonilla Garrido <miguel.bonilla@dataleanmakers.es>
 */

/**
 * Public const for geolocation management.
 */
let useGeolocation = false;

/**
 * Get the current geolocation.
 *
 * @returns {Promise}
 */
function getGeoLocation()
{
    if (!useGeolocation || !navigator.geolocation) {
        return Promise.resolve('');
    }

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            pos => resolve('latitude: ' + pos.coords.latitude + ', longitude: ' + pos.coords.longitude),
            err => {
                let message = 'Not Allowed';
                switch (err.code) {
                    case err.PERMISSION_DENIED:
                        message = 'User denied';
                        break;
                    case err.POSITION_UNAVAILABLE:
                        message = 'Location unavailable';
                        break;
                    case err.TIMEOUT:
                        message = 'Request timed out';
                        break;
                }
                resolve(message);
            }
        );
    });
}

/**
 * Set the use of geolocation.
 *
 * @param {boolean} value
 */
function setGeolocation(value)
{
    useGeolocation = value;
}
