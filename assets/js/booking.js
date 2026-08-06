/*
 * booking.js
 * ----------
 * Makes the booking page interactive without a full page reload.
 * Whenever the train/origin/destination selection changes, we call
 * our own API (api/available_seats.php) and redraw the seat grid.
 */

const trainSelect = document.getElementById("train_id");
const originSelect = document.getElementById("origin_id");
const destinationSelect = document.getElementById("destination_id");
const travelDateInput = document.getElementById("travel_date");
const seatMap = document.getElementById("seat-map");
const seatHint = document.getElementById("seat-hint");
const fareDisplay = document.getElementById("fare-display");
const confirmBtn = document.getElementById("confirm-btn");
const travelSummary = document.getElementById("travel-summary");

const hiddenTrain = document.getElementById("hidden_train_id");
const hiddenOrigin = document.getElementById("hidden_origin_id");
const hiddenDestination = document.getElementById("hidden_destination_id");
const hiddenTravelDate = document.getElementById("hidden_travel_date");
const hiddenSeatIds = document.getElementById("hidden_seat_ids");

let selectedSeatIds = [];
let currentFarePerSeat = 0;

function setSeatMapPlaceholder(message) {
    seatMap.innerHTML = `<div class="seat-map-placeholder">${message}</div>`;
    seatMap.classList.add("empty-state");
}

async function refreshSeatMap() {
    const trainId = trainSelect.value;
    const originId = originSelect.value;
    const destinationId = destinationSelect.value;
    const travelDate = travelDateInput.value || new Date().toISOString().split("T")[0];

    hiddenTrain.value = trainId;
    hiddenOrigin.value = originId;
    hiddenDestination.value = destinationId;
    hiddenTravelDate.value = travelDate;

    if (!trainId || !originId || !destinationId) {
        currentFarePerSeat = 0;
        setSeatMapPlaceholder("Choose a train, origin and destination to load the seat map.");
        seatHint.textContent = "Select a valid route to view available seats.";
        fareDisplay.textContent = "-- TZS";
        travelSummary.textContent = "Trip summary will appear here.";
        confirmBtn.disabled = true;
        return;
    }

    if (originId === destinationId) {
        currentFarePerSeat = 0;
        setSeatMapPlaceholder("Origin and destination cannot be the same station.");
        seatHint.textContent = "Choose two different stations.";
        fareDisplay.textContent = "-- TZS";
        travelSummary.textContent = "Trip summary will appear here.";
        confirmBtn.disabled = true;
        return;
    }

    const url = `api/available_seats.php?train_id=${trainId}&origin_id=${originId}&destination_id=${destinationId}&travel_date=${travelDate}`;
    const res = await fetch(url);
    const data = await res.json();

    if (data.error) {
        setSeatMapPlaceholder(data.error);
        seatHint.textContent = "Please adjust your journey details.";
        fareDisplay.textContent = "-- TZS";
        travelSummary.textContent = "Trip summary will appear here.";
        confirmBtn.disabled = true;
        return;
    }

    currentFarePerSeat = Number(data.fare || 0);
    fareDisplay.textContent = `${(currentFarePerSeat * Math.max(selectedSeatIds.length, 1)).toLocaleString()} TZS`;
    travelSummary.textContent = `${data.route_label} on ${data.travel_date} • ${data.departure_label}`;

    selectedSeatIds = [];
    hiddenSeatIds.value = "";
    confirmBtn.disabled = true;

    seatMap.classList.remove("empty-state");
    seatMap.innerHTML = "";

    const coachHeader = document.createElement("div");
    coachHeader.className = "coach-header";
    coachHeader.innerHTML = '<span>Coach</span><span>Window</span><span>Aisle</span>';
    seatMap.appendChild(coachHeader);

    const coaches = [
        { number: 1, name: "Coach 1", rows: ["A", "B", "C", "D"] },
        { number: 2, name: "Coach 2", rows: ["A", "B", "C", "D"] },
        { number: 3, name: "Coach 3", rows: ["A", "B", "C", "D"] }
    ];

    coaches.forEach((coach) => {
        const coachBlock = document.createElement("div");
        coachBlock.className = "coach-block";

        const coachTitle = document.createElement("div");
        coachTitle.className = "coach-title";
        coachTitle.textContent = coach.name;
        coachBlock.appendChild(coachTitle);

        coach.rows.forEach(rowKey => {
            const rowSeats = data.seats.filter(seat => Number(seat.coach_number) === coach.number && (seat.seat_number || "A").charAt(0).toUpperCase() === rowKey);

            const row = document.createElement("div");
            row.className = "seat-row";

            const rowLabel = document.createElement("div");
            rowLabel.className = "seat-row-label";
            rowLabel.textContent = rowKey;

            const seatsWrapper = document.createElement("div");
            seatsWrapper.className = "seat-row-seats";

            const leftWrapper = document.createElement("div");
            leftWrapper.className = "seat-group left-group";

            const rightWrapper = document.createElement("div");
            rightWrapper.className = "seat-group right-group";

            const aisle = document.createElement("div");
            aisle.className = "coach-aisle";

            rowSeats.forEach(seat => {
                const div = document.createElement("button");
                div.type = "button";
                div.textContent = seat.seat_number;
                div.className = "seat " + (seat.available ? "available" : "taken");
                div.dataset.seatId = seat.id;
                div.disabled = !seat.available;

                if (seat.available) {
                    div.addEventListener("click", () => selectSeat(div, seat.id));
                }

                const num = Number(String(seat.seat_number).replace(/\D/g, ""));
                if (num <= 3) {
                    leftWrapper.appendChild(div);
                } else {
                    rightWrapper.appendChild(div);
                }
            });

            seatsWrapper.appendChild(leftWrapper);
            seatsWrapper.appendChild(aisle);
            seatsWrapper.appendChild(rightWrapper);

            row.appendChild(rowLabel);
            row.appendChild(seatsWrapper);
            coachBlock.appendChild(row);
        });

        seatMap.appendChild(coachBlock);
    });

    const legend = document.createElement("div");
    legend.className = "seat-legend";
    legend.innerHTML = `
        <span><i class="legend-swatch available"></i> Available</span>
        <span><i class="legend-swatch taken"></i> Booked</span>
        <span><i class="legend-swatch selected"></i> Selected</span>
    `;
    seatMap.appendChild(legend);

    seatHint.textContent = "Click a green seat to select it.";
}

function selectSeat(el, seatId) {
    if (selectedSeatIds.includes(seatId)) {
        selectedSeatIds = selectedSeatIds.filter(id => id !== seatId);
        el.classList.remove("selected");
    } else {
        selectedSeatIds.push(seatId);
        el.classList.add("selected");
    }

    hiddenSeatIds.value = selectedSeatIds.join(",");
    const totalFare = (currentFarePerSeat * selectedSeatIds.length).toLocaleString();
    fareDisplay.textContent = `${totalFare} TZS`;
    confirmBtn.disabled = selectedSeatIds.length === 0;
    seatHint.textContent = selectedSeatIds.length > 0
        ? `Selected ${selectedSeatIds.length} seat${selectedSeatIds.length > 1 ? "s" : ""}. Click again to remove a seat.`
        : "Click a green seat to select it.";
}

[trainSelect, originSelect, destinationSelect, travelDateInput].forEach(el =>
    el.addEventListener("change", refreshSeatMap)
);

setSeatMapPlaceholder("Choose a train, origin and destination to load the seat map.");
