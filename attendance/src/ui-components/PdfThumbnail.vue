<script setup>
import { onMounted, ref } from "vue";
import * as pdfjsLib from "pdfjs-dist";

// ✅ Tell PDF.js where to load the worker (public path)
pdfjsLib.GlobalWorkerOptions.workerSrc = "/pdf.worker.min.js";

const props = defineProps({
  src: String,
});

const canvasRef = ref();

onMounted(async () => {
  const loadingTask = pdfjsLib.getDocument(props.src);
  const pdf = await loadingTask.promise;
  const page = await pdf.getPage(1);

  const scale = 1.5;
  const viewport = page.getViewport({ scale });

  const canvas = canvasRef.value;
  const context = canvas.getContext("2d");

  canvas.height = viewport.height;
  canvas.width = viewport.width;

  await page.render({ canvasContext: context, viewport }).promise;
});
</script>
