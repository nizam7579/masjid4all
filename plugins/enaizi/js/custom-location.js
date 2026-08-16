
document.addEventListener("DOMContentLoaded", function () {
    const fetchLocationButton = document.getElementById("fetch-location-button");
    const locationDataElement = document.getElementById("location-data");
    const mosqueResultsElement = document.getElementById("mosque-results"); // Get the mosque results element

    fetchLocationButton.addEventListener("click", function () {
        locationDataElement.innerHTML = "Please wait...";
        getUserLocation();
    });

    function getUserLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function (position) {
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;

                    console.log("Geolocation successful:");
                    console.log("Latitude:", latitude);
                    console.log("Longitude:", longitude);

                    // Send the coordinates to the server using AJAX
                    sendCoordinatesToServer(latitude, longitude);
                },
                function (error) {
                    console.error("Geolocation error:", error);
                    locationDataElement.innerHTML = "Error getting location: " + error.message;
                }
            );
        } else {
            console.error("Geolocation is not supported.");
            locationDataElement.innerHTML = "Geolocation is not supported by this browser.";
        }
    }

    function sendCoordinatesToServer(latitude, longitude) {
        jQuery.ajax({
            url: ajax_object.ajax_url, // AJAX URL from localized script
            type: 'POST',
            data: {
                action: 'get_user_location', // AJAX action name
                latitude: latitude,
                longitude: longitude,
                nonce: ajax_object.nonce // Nonce for security
            },
            success: function (response) {
                console.log("AJAX Success:", response); // Log the entire response

                if (response.success) {
                    // Display the location data on the page
                    locationDataElement.innerHTML = `
                        Latitude: ${latitude}<br>
                        Longitude: ${longitude}<br>
                        Country: ${response.data.country}<br>
                    `;

                    // Display the mosque results
                    mosqueResultsElement.innerHTML = response.data.mosque_results;
                } else {
                    locationDataElement.innerHTML = "Error: " + response.data;
                    mosqueResultsElement.innerHTML = ""; // Clear any previous results
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", error);
                console.error("Status:", status);
                console.error("Response:", xhr.responseText);
                locationDataElement.innerHTML = "AJAX Error: " + error;
                mosqueResultsElement.innerHTML = ""; // Clear any previous results
            }
        });
    }
});