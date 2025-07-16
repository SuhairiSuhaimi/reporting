(function($) {
  $(function($) {
    var latitude = $('input[name=latitude]').val();
    var longitude = $('input[name=longitude]').val();
    var initialPosition;

    if (latitude && longitude) {
      initialPosition = {
        lat: parseFloat( latitude ),
        lng: parseFloat( longitude ),
      };

    }
    else {
      initialPosition = getCurrentCoordinate();
    }

    initMap(initialPosition);
  });

}
)(jQuery);

function getCurrentCoordinate() {
  const kualaLumpur = { lat: 3.1388358, lng: 101.5221688 };
  const kuching = { lat: 1.6188407, lng: 109.9963743 };
  let initialPosition = '';

  if (!navigator.geolocation) {
    console.error('Geolocation is not supported by this browser.');
    return kualaLumpur;
  }

  navigator.geolocation.getCurrentPosition(function(position) {
    const lat = position.coords.latitude;
    const lng = position.coords.longitude;

    initialPosition = { lat: lat, lng: lng };

    return initialPosition;
  },
  function(error) {
    //console.error('Error getting location:'+ error.message);

    switch (error.code) {
      case error.PERMISSION_DENIED:
        if (location.protocol !== 'https:') {
          console.error('Geolocation is blocked because the page is not served over HTTPS.');
        }
        else {
          console.error('Permission denied by user or browser settings.');
        }
        break;

      case error.POSITION_UNAVAILABLE:
        console.error('Location information is unavailable.');
        break;

      case error.TIMEOUT:
        console.error('The request to get user location timed out.');
        break;

      case error.UNKNOWN_ERROR:
        console.error('An unknown error occurred.');
        break;

    }

    return kualaLumpur;

  });

}

let map;
let geocoder;
let marker;

async function initMap(defaultLocation) {
  waitForGoogleMaps(async function () {
    // Request needed libraries.
    const { Map } = await google.maps.importLibrary('maps');
    const { AdvancedMarkerElement } = await google.maps.importLibrary('marker');

    //const map = new google.maps.Map(document.getElementById('gmap'), {
    const map = new Map(document.getElementById('gmap'), {
      center: defaultLocation,
      zoom: 8,
      mapId: 'DEMO_MAP_ID',
    });

    //const marker = new google.maps.marker.AdvancedMarkerElement({
    const marker = new AdvancedMarkerElement({
      position: defaultLocation,
      map: map,
      title: 'Click to zoom',
      gmpDraggable: true,
    });

    geocoder = new google.maps.Geocoder();
    placeMarkerAndGeocode(defaultLocation);

    map.addListener('click', function(event) {
      // const newPos = event.latLng;
      // marker.position = newPos;
      // map.panTo(newPos); // Optional: pan to new marker location
      // placeMarkerAndGeocode(newPos);
    });

    marker.addListener('dragend', function(e) {
      const position = marker.position;

      jQuery('input[name=latitude').val(position.lat);
      jQuery('input[name=longitude').val(position.lng);
      placeMarkerAndGeocode(position);
    });

  });

}

function placeMarkerAndGeocode(latlng) {
  if (marker) {
    marker.setMap(null);
  }

  marker = new google.maps.marker.AdvancedMarkerElement({
    position: latlng,
    map: map,
  });

  geocoder.geocode({ location: latlng }, function(results, status) {
    if (status === 'OK') {
      if (results[0]) {
        var components = results[0].address_components;
        var parsed = {
          sublocality: '',
          locality: '',
          state: '',
          postcode: '',
          country: '',
        };

        let placeName = '';
        let postCode = '';

        // Loop through address components
        //for (const component of results[0].address_components) {
        components.forEach(function(component) {
          const types = component.types;

          if (types.includes('sublocality') || types.includes('sublocality_level_1')) {
            parsed.sublocality = component.long_name;
          }

          if (types.includes('locality')) {
            parsed.locality = component.long_name;
          }

          if (types.includes('administrative_area_level_1')) {
            parsed.state = component.long_name;
          }

          if (types.includes('postal_code')) {
            parsed.postcode = component.long_name;
          }

          if (types.includes('country')) {
            parsed.country = component.long_name;
          }

        });

        // Fallbacks
        if (!parsed.sublocality && results[0].formatted_address) {
          placeName = results[0].formatted_address;
        }
        else {
          if (parsed.sublocality.length > 50) {
            placeName = parsed.sublocality;
          } else {
            placeName = parsed.sublocality +', '+ parsed.locality;
          }
        }

        // Truncate placename if it exceeds 100 characters
        if (placeName.length > 100) {
          placeName = placeName.substring(0, 100);
        }

        jQuery('input[name=location]').val(placeName);

        if (parsed.postcode) {
          jQuery('input[name=postcode]').val(parsed.postcode);
        }

        console.log('placename', placeName || 'Not found');
        console.log('postcode', parsed.postcode || 'Not found');
      }
      else {
        console.error('Geocoder:  No results found');
      }

    }
    else {
      console.error('Geocoder failed due to: ' + status);
    }

  });

}

function geocodeState(stateName) {
  geocoder = new google.maps.Geocoder();
  geocoder.geocode({ address: stateName + ", Malaysia" }, function (results, status) {
    if (status === 'OK') {
      const location = results[0].geometry.location;

      jQuery('input[name=latitude').val(location.lat());
      jQuery('input[name=longitude').val(location.lng());

      initMap(location);
      placeMarkerAndGeocode(location);
    }
    else {
      console.error('Geocode was not successful for the following reason: ' + status);
    }
  });

}

function waitForGoogleMaps(callback) {
  if (typeof google !== 'undefined' && google.maps) {
    callback();
  }
  else {
    setTimeout(() => waitForGoogleMaps(callback), 100);
  }
}
