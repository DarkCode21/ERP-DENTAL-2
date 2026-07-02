/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 *
 * @author Miguel Bonilla Garrido <miguel.bonilla@dataleanmakers.es>
 */

/**
 *
 * @returns {Promise<void>}
 */
async function initMap()
{
  const mapEl = document.getElementById('map');
  if (!mapEl) {
    return;
  }
  let locStr = mapEl.dataset.location || '';
  if (!locStr.startsWith('latitude:')) {
    mapEl.innerHTML = '<div style="padding: 1rem; text-align: center; color: #dc3545;">Geolocalización no válida</div>';
    return;
  }

  // Parsear latitud y longitud
  const [lat, lng] = locStr
    .replace('latitude: ', '')
    .replace('longitude: ', '')
    .split(',')
    .map(parseFloat);

  // Inicializar mapa
  const map = L.map('map').setView([lat, lng], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  // Añadir marcador
  const marker = L.marker([lat, lng]).addTo(map);

  // Control de geocoder inverso, si está cargado
  if (L.Control && L.Control.geocoder) {
    const geocoderControl = L.Control.geocoder({ defaultMarkGeocode: false }).addTo(map);
    geocoderControl.options.geocoder
      .reverse({ lat, lng }, map.options.crs.scale(map.getZoom()))
      .then(results => {
        if (results && results.length) {
          marker.bindPopup(results[0].name).openPopup();
        } else {
          marker.bindPopup('Ubicación desconocida').openPopup();
        }
      })
      .catch(() => {
        marker.bindPopup('Error al geocodificar').openPopup();
      });
  }
}

/**
 * Set geolocation.
 */
document.addEventListener('DOMContentLoaded', () => {
  setGeolocation(true);
  initMap();
});
