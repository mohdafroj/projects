<template>
  <div>
    <div id="contentToPrint">
      <h1>Hello {{ name }}</h1>
      <p>This content will be in PDF.</p>
    </div>
    <button @click="generatePdfFromHtml">Download PDF</button>
  </div>
</template>

<script setup>
import { ref } from "vue";
import jsPDF from "jspdf";
import html2canvas from "html2canvas"; // also install with npm

const name = ref("User");

const generatePdfFromHtml = () => {
  const doc = new jsPDF();

  const element = document.getElementById("contentToPrint");

  html2canvas(element).then((canvas) => {
    const imgData = canvas.toDataURL("image/png");
    const imgProps = doc.getImageProperties(imgData);
    const pdfWidth = doc.internal.pageSize.getWidth();
    const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

    doc.addImage(imgData, "PNG", 0, 0, pdfWidth, pdfHeight);
    doc.save("document.pdf");
  });
};
</script>
