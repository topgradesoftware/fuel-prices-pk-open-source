<script setup lang="ts">
import TimelineLayout from './TimelineLayout.vue';
import { ref, computed } from 'vue';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend, BarController, LineController } from 'chart.js';
import { Chart } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend, BarController, LineController);

defineProps<{
    indicators: any[];
}>();

const chartData = {
  labels: ['2018', '2019', '2020', '2021', '2022', '2023', '2024'],
  datasets: [
    {
      type: 'line' as const,
      label: 'Inflation (%)',
      borderColor: '#dc2626',
      backgroundColor: '#dc2626',
      yAxisID: 'y1',
      data: [5.8, 6.2, 8.2, 9.7, 12.2, 27.4, 20.7],
      tension: 0.3
    },
    {
      type: 'bar' as const,
      label: 'GDP Growth (%)',
      backgroundColor: '#10b981',
      yAxisID: 'y',
      data: [6.1, 3.1, -0.9, 5.8, 6.2, -0.2, 2.0]
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
      title: { display: true, text: 'GDP Growth (%)' }
    },
    y1: {
      type: 'linear' as const,
      display: true,
      position: 'right' as const,
      grid: { drawOnChartArea: false },
      title: { display: true, text: 'Inflation (%)' }
    }
  }
};

// Phase 7: Fuel Affordability Index
const affordabilityData = {
  labels: ['2018', '2019', '2020', '2021', '2022', '2023', '2024'],
  datasets: [
    {
      type: 'line' as const,
      label: 'Litres per Minimum Wage',
      borderColor: '#3b82f6',
      backgroundColor: 'rgba(59, 130, 246, 0.1)',
      fill: true,
      data: [157.5, 164.9, 234.8, 169.3, 105.9, 110.2, 114.4], // (Minimum wage / Avg petrol price)
      tension: 0.3
    }
  ]
};

const affordabilityOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'top' as const },
  },
  scales: {
    y: {
      title: { display: true, text: 'Litres of Petrol' }
    }
  }
};
</script>

<template>
    <TimelineLayout>
        <div class="tab-content">
            <h2>Economic Indicators</h2>
            <p>Track GDP Growth against Inflation over the years (Source: PBS & SBP).</p>
            
            <div class="chart-container">
                <Chart type="bar" :data="chartData" :options="chartOptions" />
            </div>

            <!-- Phase 7: Fuel Affordability Index -->
            <div class="affordability-section">
                <h2>Fuel Affordability Index</h2>
                <p>How many litres of petrol a Federal Minimum Wage earner could buy each year.</p>
                <div class="chart-container">
                    <Chart type="line" :data="affordabilityData" :options="affordabilityOptions" />
                </div>
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

.affordability-section {
    margin-top: 48px;
}

.affordability-section h2 {
    font-size: 1.5rem;
    margin-bottom: 8px;
}
</style>
