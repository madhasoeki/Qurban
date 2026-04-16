document.addEventListener("alpine:init", () => {
    window.Alpine.data("dashboardDurations", () => ({
        nowTimestamp: Math.floor(Date.now() / 1000),
        tickerId: null,

        init() {
            this.tickerId = setInterval(() => {
                this.nowTimestamp += 1;
            }, 1000);
        },

        destroy() {
            if (this.tickerId) {
                clearInterval(this.tickerId);
            }
        },

        stageDurationLabel(startTimestamp, finishTimestamp = null) {
            if (startTimestamp === null) {
                return "Belum Dimulai";
            }

            const endTimestamp = finishTimestamp ?? this.nowTimestamp;
            const durationSeconds = Math.max(0, endTimestamp - startTimestamp);
            const formattedDuration = this.formatDuration(durationSeconds);

            if (finishTimestamp !== null) {
                return `Selesai - ${formattedDuration}`;
            }

            return formattedDuration;
        },

        stageDurationValue(startTimestamp, finishTimestamp = null) {
            if (startTimestamp === null) {
                return "-";
            }

            const endTimestamp = finishTimestamp ?? this.nowTimestamp;
            const durationSeconds = Math.max(0, endTimestamp - startTimestamp);

            return this.formatDuration(durationSeconds);
        },

        formatDuration(totalSeconds) {
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;

            return [hours, minutes, seconds]
                .map((segment) => segment.toString().padStart(2, "0"))
                .join(":");
        },
    }));
});
