let colors = {
  primary: "#4669FA",
  secondary: "#A0AEC0",
  danger: "#F1595C",
  black: "#111112",
  warning: "#FA916B",
  info: "#0CE7FA",
  light: "#475569",
  success: "#50C793",
  "gray-f7": "#F7F8FC",
  dark: "#1E293B",
  "dark-gray": "#0F172A",
  gray: "#68768A",
  gray2: "#EEF1F9",
  "dark-light": "#CBD5E1",
  purple: "#A3A1FB",
};

// Column Chart 2
export const columnCharthome2 = {
  series: [
    {
      name: "Revenue",
      data: [40, 70, 45, 100, 75, 40, 80, 90],
    },
  ],
  chartOptions: {
    chart: {
      type: "bar",
      animations: {
        enabled: true,
        speed: 800,
        animateGradually: {
            enabled: true,
            delay: 150
        },
      },
      toolbar: { show: false },
      zoom: { enabled: false },
      sparkline: { enabled: true },
    },
    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: "30%",
        barHeight: "60%",
        distributed: false,
      },
    },
    legend: { show: false },
    dataLabels: { enabled: false },
    stroke: { show: false },
    fill: { opacity: 1 },
    tooltip: {
      y: {
        formatter: (val) => `$ ${val}k`,
      },
    },
    yaxis: { show: false },
    xaxis: {
      show: false,
      labels: { show: false },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    colors: [colors.warning],
    grid: { show: false },
  },
};

// ✅ Column Chart 3
export const columnCharthome3 = {
  series: [
    {
      name: "Revenue",
      data: [35, 60, 50, 90, 80, 45, 70, 100],
    },
  ],
  chartOptions: {
    chart: {
      type: "bar",
      animations: {
        enabled: true,
        easing: "easeinout",
        speed: 1000,
        animateGradually: { enabled: true, delay: 150 },
        dynamicAnimation: { enabled: true, speed: 500 },
      },
      toolbar: { show: false },
      zoom: { enabled: false },
      sparkline: { enabled: true },
    },
    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: "30%",
        barHeight: "60%",
        distributed: false,
      },
    },
    legend: { show: false },
    dataLabels: { enabled: false },
    stroke: { show: false },
    fill: { opacity: 1 },
    tooltip: {
      y: {
        formatter: (val) => `$ ${val}k`,
      },
    },
    yaxis: { show: false },
    xaxis: {
      show: false,
      labels: { show: false },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    colors: [colors.info],
    grid: { show: false },
  },
};

// ✅ Column Chart 4
export const columnCharthome4 = {
  series: [
    {
      name: "Revenue",
      data: [50, 80, 60, 90, 70, 40, 85, 95],
    },
  ],
  chartOptions: {
    chart: {
      type: "bar",
      animations: {
        enabled: true,
        easing: "easeinout",
        speed: 1000,
        animateGradually: { enabled: true, delay: 150 },
        dynamicAnimation: { enabled: true, speed: 500 },
      },
      toolbar: { show: false },
      zoom: { enabled: false },
      sparkline: { enabled: true },
    },
    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: "30%",
        barHeight: "60%",
        distributed: false,
      },
    },
    legend: { show: false },
    dataLabels: { enabled: false },
    stroke: { show: false },
    fill: { opacity: 1 },
    tooltip: {
      y: {
        formatter: (val) => `$ ${val}k`,
      },
    },
    yaxis: { show: false },
    xaxis: {
      show: false,
      labels: { show: false },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    colors: [colors.success],
    grid: { show: false },
  },
};
