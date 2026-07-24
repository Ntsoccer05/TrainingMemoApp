import { ref, onMounted, onUnmounted } from "vue";

// RDSが毎日1:00〜4:00(JST)の間停止するため、その時間帯はメンテナンス中として表示する
const MAINTENANCE_START_HOUR = 1;
const MAINTENANCE_END_HOUR = 4;

const isJstMaintenanceHour = (): boolean => {
    const jstHour = Number(
        new Intl.DateTimeFormat("en-US", {
            hour: "numeric",
            hour12: false,
            timeZone: "Asia/Tokyo",
        }).format(new Date())
    );
    return jstHour >= MAINTENANCE_START_HOUR && jstHour < MAINTENANCE_END_HOUR;
};

export default function useMaintenanceMode() {
    const isMaintenance = ref<boolean>(isJstMaintenanceHour());
    let timerId: ReturnType<typeof setInterval> | undefined;

    onMounted(() => {
        // 境界(1:00, 4:00)をまたいだ場合に画面へ反映させるため定期的に再判定する
        timerId = setInterval(() => {
            isMaintenance.value = isJstMaintenanceHour();
        }, 60 * 1000);
    });

    onUnmounted(() => {
        if (timerId) {
            clearInterval(timerId);
        }
    });

    return { isMaintenance };
}
