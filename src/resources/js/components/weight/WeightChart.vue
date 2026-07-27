<template>
  <VueApexCharts type="line" height="280" :options="chartOptions" :series="series" />
</template>

<script setup lang="ts">
import { computed, ComputedRef } from "vue";
import VueApexCharts from "vue3-apexcharts";
import { WeightRecord } from "../../types/weight";

const props = defineProps<{
  records: WeightRecord[];
  targetWeight: number | null;
}>();

const emits = defineEmits<{
  (e: "pointClick", record: WeightRecord): void;
}>();

const series: ComputedRef<{ name: string; data: (number | null)[] }[]> = computed(() => [
  {
    name: "体重(kg)",
    data: props.records.map((r) => r.bodyWeight),
  },
]);

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
