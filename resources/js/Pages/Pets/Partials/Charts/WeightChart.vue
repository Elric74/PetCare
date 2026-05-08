<script setup>
import { onMounted, ref } from 'vue';
import { Chart as ChartJS, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js';
import { Line } from 'vue-chartjs';
import moment from 'moment';

ChartJS.register(LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

const props = defineProps({
  pet: {
    type: Object,
    required: true
  }
});

const chartData = ref(null);
const hasBirthDate = ref(false);
const chartOptions = ref({
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    title: {
      display: true,
      text: 'Évolution du poids',
      font: {
        size: 14
      }
    },
    legend: {
      display: true,
      position: 'top'
    },
    tooltip: {
      callbacks: {
        label: function(context) {
          let label = 'Poids: ';
          if (context.parsed.y !== null) {
            label += Math.round(context.parsed.y) + ' g';
          }
          return label;
        },
        title: function(context) {
          if (context[0]) {
            const days = Math.round(context[0].parsed.x);
            if (hasBirthDate.value && days === 0) {
              return 'Naissance (Jour 0)';
            }
            return `Jour ${days}`;
          }
          return '';
        }
      }
    }
  },
  scales: {
    x: {
      type: 'linear',
      position: 'bottom',
      title: {
        display: true,
        text: 'Jours',
        font: {
          size: 12
        }
      },
      min: 0,
      ticks: {
        maxTicksLimit: 10
      }
    },
    y: {
      title: {
        display: true,
        text: 'Poids (grammes)',
        font: {
          size: 12
        }
      },
      beginAtZero: false
    }
  }
});

const buildChartData = () => {
  if (!props.pet.medical_history || props.pet.medical_history.length === 0) {
    chartData.value = null;
    return;
  }

  const birthDate = props.pet.birth_date ? moment(props.pet.birth_date) : null;
  hasBirthDate.value = !!birthDate;

  // Filter histories that have weight_g and diagnosis_date
  const weightRecords = props.pet.medical_history
    .filter((h) => h.weight_g !== null && h.weight_g !== '' && h.diagnosis_date)
    .sort((a, b) => new Date(a.diagnosis_date) - new Date(b.diagnosis_date));

  // Prepare data points with x (days) and y (weight)
  const dataPoints = [];

  // Add birth date point (day 0) if we have birth weight info
  // Otherwise start from first medical record
  const referenceDate = birthDate || (weightRecords.length > 0 ? moment(weightRecords[0].diagnosis_date) : null);

  weightRecords.forEach(record => {
    const recordDate = moment(record.diagnosis_date);
    const daysSinceBirth = referenceDate ? recordDate.diff(referenceDate, 'days') : 0;

    dataPoints.push({
      x: daysSinceBirth,
      y: record.weight_g
    });
  });

  if (dataPoints.length === 0) {
    chartData.value = null;
    return;
  }

  chartData.value = {
    datasets: [
      {
        label: 'Poids (g)',
        data: dataPoints,
        borderColor: '#4f46e5',
        backgroundColor: 'rgba(79, 70, 229, 0.1)',
        tension: 0.3,
        fill: true,
        pointRadius: 5,
        pointBackgroundColor: '#4f46e5',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverRadius: 7
      }
    ]
  };

  chartOptions.value.scales.x.title.text = hasBirthDate.value
    ? 'Jours depuis la naissance'
    : 'Jours depuis le premier relevé';
};

onMounted(() => {
  buildChartData();
});
</script>

<template>
  <div class="px-4 py-5 sm:p-6">
    <div v-if="chartData" class="w-full h-96">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Suivi du poids</h3>
      <Line :data="chartData" :options="chartOptions" />
    </div>
    <div v-else class="text-center text-gray-500 py-8">
      <p>Aucune donnée de poids disponible. Ajoutez des enregistrements de poids dans l'historique médical.</p>
    </div>
  </div>
</template>
