<script setup lang="ts">
import TimelineLayout from './TimelineLayout.vue';
import { ref, computed } from 'vue';
import { Line } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

const props = defineProps<{
    fuelHistory: any[];
}>();

const rawLabels = props.fuelHistory.map(item => item.date);
const rawPetrol = props.fuelHistory.map(item => item.petrol);
const rawDiesel = props.fuelHistory.map(item => item.diesel);

const currentLabels = ref([...rawLabels]);
const currentPetrol = ref([...rawPetrol]);
const currentDiesel = ref([...rawDiesel]);

const chartData = computed(() => ({
  labels: currentLabels.value,
  datasets: [
    {
      label: 'Petrol Price (PKR)',
      backgroundColor: '#2563eb',
      borderColor: '#2563eb',
      data: currentPetrol.value,
      tension: 0.3
    },
    {
      label: 'Diesel Price (PKR)',
      backgroundColor: '#f59e0b',
      borderColor: '#f59e0b',
      data: currentDiesel.value,
      tension: 0.3
    }
  ]
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'top' as const },
    tooltip: { mode: 'index' as const, intersect: false }
  },
  animation: { duration: 400 } // smooth animation during playback
};

// Playback State
const isPlaying = ref(false);
const playIndex = ref(rawLabels.length);
let playInterval: any = null;

const startPlayback = () => {
    if (playIndex.value >= rawLabels.length) {
        // Reset to beginning if at the end
        playIndex.value = 1;
    }
    isPlaying.value = true;
    updateChartData();
    playInterval = setInterval(() => {
        if (playIndex.value < rawLabels.length) {
            playIndex.value++;
            updateChartData();
        } else {
            pausePlayback();
        }
    }, 1000); // 1 second per jump
};

const pausePlayback = () => {
    isPlaying.value = false;
    if (playInterval) clearInterval(playInterval);
};

const resetPlayback = () => {
    pausePlayback();
    playIndex.value = rawLabels.length;
    updateChartData();
};

const updateChartData = () => {
    currentLabels.value = rawLabels.slice(0, playIndex.value);
    currentPetrol.value = rawPetrol.slice(0, playIndex.value);
    currentDiesel.value = rawDiesel.slice(0, playIndex.value);
};
</script>

<template>
    <TimelineLayout>
        <div class="tab-content">
            <div class="header-with-playback">
                <div>
                    <h2>Fuel History</h2>
                    <p>Compare historical petrol and diesel prices over time.</p>
                </div>
                <div class="playback-controls">
                    <button class="btn-play" @click="isPlaying ? pausePlayback() : startPlayback()">
                        {{ isPlaying ? '⏸ Pause' : '▶ Play Timeline' }}
                    </button>
                    <button class="btn-reset" @click="resetPlayback" :disabled="isPlaying">↺ Reset</button>
                </div>
            </div>
            
            <div class="chart-container">
                <Line :data="chartData" :options="chartOptions" />
            </div>

            <!-- Phase 5: Price Change Breakdown (Placeholder) -->
            <div class="breakdown-section" v-if="fuelHistory.length > 0">
                <h3>Price Change Breakdown</h3>
                <p class="breakdown-desc">Deterministic arithmetic based on OGRA's public price build-up formula.</p>
                <div class="breakdown-card">
                    <div class="breakdown-header">
                        <span>Latest Change:</span>
                        <strong>Rs +18.00</strong>
                    </div>
                    <ul class="breakdown-list">
                        <li><span>Import Parity (Brent/FX):</span> <span class="val positive">+ Rs 9.00</span></li>
                        <li><span>Petroleum Levy:</span> <span class="val positive">+ Rs 4.00</span></li>
                        <li><span>GST / Taxes:</span> <span class="val">Rs 0.00</span></li>
                        <li><span>Distribution Margin:</span> <span class="val positive">+ Rs 5.00</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </TimelineLayout>
</template>

<style scoped>
.chart-container {
    height: 400px;
    margin-top: 16px;
    padding: 16px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}

.header-with-playback {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.playback-controls {
    display: flex;
    gap: 8px;
}

.playback-controls button {
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.btn-play {
    background: #2563eb;
    color: white;
}

.btn-play:hover {
    background: #1d4ed8;
}

.btn-reset {
    background: #e2e8f0;
    color: #475569;
}

.btn-reset:hover:not(:disabled) {
    background: #cbd5e1;
}

.btn-reset:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.breakdown-section {
    margin-top: 32px;
}

.breakdown-desc {
    color: #6b7280;
    font-size: 0.875rem;
    margin-bottom: 16px;
}

.breakdown-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    max-width: 400px;
}

.breakdown-header {
    display: flex;
    justify-content: space-between;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 12px;
    font-size: 1.125rem;
}

.breakdown-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.breakdown-list li {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 0.95rem;
    color: #374151;
}

.breakdown-list .val.positive {
    color: #b91c1c;
    font-weight: 500;
}

.breakdown-list .val.negative {
    color: #047857;
    font-weight: 500;
}
</style>
