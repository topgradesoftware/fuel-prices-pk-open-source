<script setup lang="ts">
import TimelineLayout from './TimelineLayout.vue';
import { ref, computed } from 'vue';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js';
import { Line } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

defineProps<{
    governments: any[];
}>();

const chartData = {
  labels: ['2018', '2019', '2020', '2021', '2022', '2023', '2024'],
  datasets: [
    {
      label: 'USD / PKR Exchange Rate',
      borderColor: '#8b5cf6',
      backgroundColor: '#8b5cf6',
      data: [123.6, 138.9, 167.4, 157.3, 228.4, 287.5, 278.2],
      tension: 0.3,
      fill: true
    }
  ]
};

// Creating a custom plugin to draw background regions for governments
const governmentBgPlugin = {
  id: 'governmentBgPlugin',
  beforeDraw: (chart: any) => {
    const ctx = chart.ctx;
    const xAxis = chart.scales.x;
    const yAxis = chart.scales.y;
    
    const drawRegion = (startIdx: number, endIdx: number, color: string, label: string) => {
      const startX = xAxis.getPixelForTick(startIdx);
      const endX = xAxis.getPixelForTick(endIdx);
      
      ctx.save();
      ctx.fillStyle = color;
      ctx.fillRect(startX, yAxis.top, endX - startX, yAxis.bottom - yAxis.top);
      
      // Draw label
      ctx.fillStyle = '#4b5563';
      ctx.font = 'bold 12px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(label, startX + (endX - startX) / 2, yAxis.top + 20);
      ctx.restore();
    };

    // Example bounds (indices of labels array)
    // 2018 (0) to 2022 (4) = PTI
    // 2022 (4) to 2024 (6) = PDM / Caretaker
    drawRegion(0, 4, 'rgba(16, 185, 129, 0.1)', 'PTI Tenure');
    drawRegion(4, 6, 'rgba(239, 68, 68, 0.1)', 'PDM & Caretaker');
  }
};

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom' as const }
  }
};
</script>

<template>
    <TimelineLayout>
        <div class="tab-content">
            <h2>Government Timeline</h2>
            <p>Compare macroeconomic performance (like USD/PKR rate) across political tenures.</p>
            
            <div class="chart-container">
                <Line 
                    :data="chartData" 
                    :options="chartOptions" 
                    :plugins="[governmentBgPlugin]"
                />
            </div>

            <!-- Phase 5: Government Period Averages Table -->
            <div class="averages-section">
                <h3>Tenure Averages</h3>
                <div class="disclaimer-alert">
                    <strong>Disclaimer:</strong> Figures below are time-weighted averages. They are not adjusted for global oil prices or currency movements during that window. Cross-period comparison reflects world conditions as much as domestic policy.
                </div>
                
                <div class="table-responsive">
                    <table class="averages-table">
                        <thead>
                            <tr>
                                <th>Government</th>
                                <th>Tenure</th>
                                <th>Avg Petrol (PKR)</th>
                                <th>Avg Brent (USD)</th>
                                <th>Avg USD/PKR</th>
                                <th>Avg Inflation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dummy structure until real data arrives -->
                            <tr>
                                <td>PTI (Elected)</td>
                                <td>Aug 2018 - Apr 2022</td>
                                <td>110.45</td>
                                <td>58.20</td>
                                <td>155.30</td>
                                <td>8.9%</td>
                            </tr>
                            <tr>
                                <td>PDM (Transition)</td>
                                <td>Apr 2022 - Aug 2023</td>
                                <td>245.10</td>
                                <td>94.50</td>
                                <td>240.80</td>
                                <td>24.5%</td>
                            </tr>
                            <tr>
                                <td>Caretaker</td>
                                <td>Aug 2023 - Mar 2024</td>
                                <td>285.30</td>
                                <td>82.10</td>
                                <td>285.90</td>
                                <td>28.3%</td>
                            </tr>
                        </tbody>
                    </table>
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

.averages-section {
    margin-top: 48px;
}

.averages-section h3 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 12px;
}

.disclaimer-alert {
    background-color: #f3f4f6;
    border-left: 4px solid #6b7280;
    padding: 12px 16px;
    font-size: 0.875rem;
    color: #4b5563;
    margin-bottom: 24px;
    border-radius: 0 4px 4px 0;
}

.table-responsive {
    overflow-x: auto;
}

.averages-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.averages-table th, .averages-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.averages-table th {
    background-color: #f9fafb;
    font-weight: 600;
    color: #374151;
}

.averages-table tr:hover {
    background-color: #f3f4f6;
}
</style>
