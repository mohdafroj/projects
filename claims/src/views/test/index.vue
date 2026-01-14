<template>
  <div>
    <textarea v-model="text" rows="10" cols="50" placeholder="Write your content here..."></textarea>
    <br />
    <button @click="generateAndUploadPDF">Generate & Upload PDF</button>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { jsPDF } from 'jspdf';

// Reactive state for the text content
const text = ref('');

// Function to generate PDF and upload
async function generateAndUploadPDF() {
  if (!text.value.trim()) {
    alert('Please enter some text first.');
    return;
  }

  // Create jsPDF instance
  const doc = new jsPDF();

  // Add the text to PDF starting at x=10, y=10
  doc.text(text.value, 10, 10);

  // Export PDF as Blob (not download)
  const pdfBlob = doc.output('blob');

  // Prepare FormData to send file
  const formData = new FormData();
  formData.append('file', pdfBlob, 'document.pdf');
console.log("THIS IS FORM DATA",formData);
  try {
    // Send the file via POST to your Laravel API
    const response = await fetch('http://your-laravel-api.test/api/upload', {
      method: 'POST',
      body: formData,
    });

    const result = await response.json();

    if (response.ok) {
      alert('PDF uploaded successfully!');
      console.log(result);
    } else {
      alert('Upload failed: ' + (result.message || 'Unknown error'));
    }
  } catch (error) {
    alert('Upload error: ' + error.message);
    console.error(error);
  }
}
</script>
