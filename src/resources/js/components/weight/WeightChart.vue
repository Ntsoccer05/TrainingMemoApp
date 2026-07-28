<template>
  <VueApexCharts type="line" :height="chartHeight" :options="chartOptions" :series="series" />
</template>

<script setup lang="ts">
import { computed, ComputedRef, onMounted, onUnmounted, ref, Ref } from "vue";
import VueApexCharts from "vue3-apexcharts";
import { WeightRecord } from "../../types/weight";

const props = defineProps<{
  records: WeightRecord[];
  targetWeight: number | null;
}>();

const emits = defineEmits<{
  (e: "pointClick", record: WeightRecord): void;
}>();

const MOBILE_BREAKPOINT = 768;
const MOBILE_CHART_HEIGHT = 220;
const DESKTOP_CHART_HEIGHT = 280;

const chartHeight: Ref<number> = ref(
  window.innerWidth < MOBILE_BREAKPOINT ? MOBILE_CHART_HEIGHT : DESKTOP_CHART_HEIGHT
);

const updateChartHeight = (): void => {
  chartHeight.value =
    window.innerWidth < MOBILE_BREAKPOINT ? MOBILE_CHART_HEIGHT : DESKTOP_CHART_HEIGHT;
};

onMounted(() => {
  window.addEventListener("resize", updateChartHeight);
});

onUnmounted(() => {
  window.removeEventListener("resize", updateChartHeight);
});

const series: ComputedRef<{ name: string; data: (number | null)[] }[]> = computed(() => [
  {
    name: "体重(kg)",
    data: props.records.map((r) => r.bodyWeight),
  },
]);

const yAxisRange: ComputedRef<{ min?: number; max?: number }> = computed(() => {
  const values: number[] = props.records
    .map((r) => r.bodyWeight)
    .filter((v): v is number => v !== null);

  if (props.targetWeight !== null) {
    values.push(props.targetWeight);
  }

  if (values.length === 0) {
    return {};
  }

  const min = Math.min(...values);
  const max = Math.max(...values);

  return {
    min: Math.floor((min - 1) * 10) / 10,
    max: Math.ceil((max + 1) * 10) / 10,
  };
});

const chartOptions = computed(() => {
  const annotations =
    props.targetWeight !== null
      ? {
          yaxis: [
            {
              y: props.targetWeight,
              borderColor: "#f97316",
              label: {
                text: `目標体重 ${props.targetWeight}kg`,
                style: { color: "#fff", background: "#f97316" },
              },
            },
          ],
        }
      : {};

  return {
    chart: {
      id: "weight-chart",
      toolbar: { show: false },
      events: {
        dataPointSelection: (
          _event: unknown,
          _chartContext: unknown,
          config: { dataPointIndex: number }
        ) => {
          const record = props.records[config.dataPointIndex];
          if (record) {
            emits("pointClick", record);
          }
        },
      },
    },
    xaxis: {
      categories: props.records.map((r) => r.recorded_at),
    },
    yaxis: {
      ...yAxisRange.value,
      labels: {
        formatter: (val: number) => `${val}kg`,
      },
    },
    stroke: { curve: "smooth", width: 2 },
    markers: { size: 4 },
    annotations,
  };
});
</script>
