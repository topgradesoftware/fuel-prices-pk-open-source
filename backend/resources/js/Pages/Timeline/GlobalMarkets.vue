<script setup lang="ts">
import TimelineLayout from './TimelineLayout.vue';
import { ref, computed } from 'vue';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js';
import { Line } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

defineProps<{
    markets: any[];
}>();

const chartData = {
  labels: ['Jan 2018', 'Jun 2019', 'Mar 2020', 'Jul 2021', 'Sep 2022', 'Aug 2023', 'Mar 2024'],
  datasets: [
    {
      label: 'Brent Crude (USD)',
      borderColor: '#4b5563',
      backgroundColor: '#4b5563',
      yAxisID: 'y1',
      data: [71.8, 53.8, 38.3, 75.2, 91.8, 86.7, 83.5],
      tension: 0.3
    },
    {
      label: 'Local Petrol (PKR)',
      borderColor: '#2563eb',
      backgroundColor: '#2563eb',
      yAxisID: 'y',
      data: [95.24, 90.97, 74.52, 118.09, 235.98, 290.45, 279.75],
      tension: 0.3
    }
  ]
};

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index' as const, intersect: false },
  scales: {
    y: {
      type: 'linear' as const,
      display: true,
      position: 'left' as const,
      title: { display: true, text: 'Petrol Price (PKR)' }
    },
    y1: {
      type: 'linear' as const,
      display: true,
      position: 'right' as const,
      grid: { drawOnChartArea: false },
      title: { display: true, text: 'Brent Crude (USD)' }
    }
  }
};
</script>

<template>
    <TimelineLayout>
        <div class="tab-content">
            <h2>Global Markets Correlation</h2>
            <p>Overlay Brent Crude against local fuel prices to see the impact of global markets.</p>
            
            <div class="chart-container">
                <Line :data="chartData" :options="chartOptions" />
            </div>
        </div>
    </TimelineLayout>
</template>

<style scoped>
.chart-container {
    height: 400px;
    margin-top: 24px;
    padding: 16px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}
</style>
