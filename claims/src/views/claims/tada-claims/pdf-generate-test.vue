<template>
  <div class="reimbursement-claim-generator">
    <button 
      @click="downloadPDF" 
      class="generate-btn"
      :disabled="loading"
    >
      {{ loading ? 'Generating PDF...' : 'Generate Reimbursement Claim PDF' }}
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

// Reactive flags
const loading = ref(false)
const imagesLoaded = ref(false)

// pdfMake instance
let pdfMake = null

onMounted(() => {
  if (window.pdfMake) {
    pdfMake = window.pdfMake
    if (!pdfMake.vfs) {
      pdfMake.vfs = {}
    }
    loadImagesFromPublic()
  } else {
    loadPdfMake()
  }
})

const loadPdfMake = async () => {
  try {
    const pdfMakeModule = await import('pdfmake/build/pdfmake')
    const pdfFonts = await import('pdfmake/build/vfs_fonts')
    pdfMake = pdfMakeModule.default || pdfMakeModule
    pdfMake.vfs = pdfFonts.default || pdfFonts.vfs || pdfFonts || {}
    await loadImagesFromPublic()
  } catch (error) {
    console.error('Failed to load pdfMake:', error.message)
    pdfMake.vfs = {}
    await loadImagesFromPublic()
  }
}

const loadImagesFromPublic = async () => {
  try {
    if (!pdfMake.vfs) {
      pdfMake.vfs = {};
    }

    const topImagePath = '/assets/images/all-img/top-curve.png'; // Fixed path
    const bottomImagePath = '/assets/images/all-img/bottom-line.png'; // Fixed path
    const logoPath = '/assets/images/all-img/logo.png'; // Fixed path
    const watermarkPath = '/assets/images/all-img/watermark.png'; // Fixed path

    const loadImage = async (path, key) => {
      try {
        const fullUrl = `${window.location.origin}${path}`;
        const response = await fetch(fullUrl);
        if (!response.ok) {
          throw new Error(`Image not found at ${path}`);
        }
        const blob = await response.blob();
        const base64 = await blobToBase64(blob);
        pdfMake.vfs[key] = base64.split(',')[1];
        console.log(`Image loaded successfully: ${key}`); // Debug log
      } catch (error) {
        console.warn(`Failed to load image at ${path}:`, error.message);
        throw error; // Rethrow to track failures
      }
    };

    const imagePromises = [
      loadImage(topImagePath, 'top-curve.png'),
      loadImage(bottomImagePath, 'bottom-line.png'),
      loadImage(logoPath, 'logo.png'),
      loadImage(watermarkPath, 'watermark.png'),
    ];

    const results = await Promise.allSettled(imagePromises);
    results.forEach((result, index) => {
      if (result.status === 'rejected') {
        console.error(`Failed to load image ${index}:`, result.reason);
      }
    });

    imagesLoaded.value = results.some(result => result.status === 'fulfilled');
  } catch (error) {
    console.error('Error loading images:', error.message);
    imagesLoaded.value = false;
  }
};

const blobToBase64 = (blob) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onloadend = () => resolve(reader.result)
    reader.onerror = reject
    reader.readAsDataURL(blob)
  })
}

// Reimbursement claim data
const claimData = ref({
  memberName: 'Member Name',
  memberState: 'State',
  memberAddress: 'Member Address',
  memberEmail: 'member@email.com',
  memberMobile: '+91 0000000000',
  memberTelephone: '+91 0000000000',
  committeeName: 'Committee Name',
  trackNumber: 'TRACK0001',
  submissionDate: '16/09/2025',
  itemName: 'Electronic Products',
  claimAmount: '50000.00'
})

const downloadPDF = async () => {
  if (!pdfMake) {
    alert('PDF library is not loaded yet. Please try again.')
    return
  }

  loading.value = true

  try {
    const fileName = `Reimbursement_Claim_${claimData.value.trackNumber}_${formatDateForFileName(new Date())}.pdf`

    const docDefinition = {
      pageSize: 'A4',
      pageMargins: [0, 0, 0, 0], // Adjusted margins to match design
      background: imagesLoaded.value ? [
        {
          image: 'watermark.png',
          width:400,
          opacity: 0.7,
          absolutePosition: { x: 100, y: 200 },
          margin:[0, 50, 0, 0]
        }
      ] : [],
      content: [
        // Top image with logo centered below curve
        {
          stack: [
            {
              image: imagesLoaded.value ? 'top-curve.png' : null,
              width: 600, // Adjusted to fit within margins
              height: 60
            },
            {
              image: imagesLoaded.value ? 'logo.png' : null,
              width: 70,
              height: 110,
              absolutePosition: { x: 237.5, y: 35 } // Adjusted x position for new margins
            }
          ]
        },

        // Member details header
        {
          columns: [
            {
              width: '40%',
              stack: [
                { text: claimData.value.memberName, style: 'memberName',margin: [20, 40, 20, 10] },
                { text: 'Member (Rajya Sabha)', style: 'memberTitle',margin: [20, 0, 20, 10] },
                { text: claimData.value.memberState, style: 'state',margin: [20, 0, 20, 10] },
                { text: `Member of committee: ${claimData.value.committeeName}`, style: 'committee',margin: [20, 0, 20, 10] }
              ]
            },
            {
              width: '60%',
              alignment: 'right',
              stack: [
                { text: `Address: ${claimData.value.memberAddress}`, style: 'address', margin: [20, 20, 20, 10] },
                { text: `Email: ${claimData.value.memberEmail}`, style: 'contact', margin: [20, 0, 20, 10] },
                { text: `Mob: ${claimData.value.memberMobile}`, style: 'contact', margin: [20, 0, 20, 10] },
                { text: `Tel: ${claimData.value.memberTelephone}`, style: 'contact', margin: [20, 0, 20, 10],},
            { 
              width: '*', 
              text: `Reference Number: ${claimData.value.trackNumber}`, 
              style: 'refNum' ,
            margin: [20, 0, 20, 10]
            },
            { 
              width: 'auto', 
              text: `Date: ${claimData.value.submissionDate}`, 
              alignment: 'right', 
              style: 'date' ,
             margin: [20, 0, 20, 10]
            }
              ]
            }
          ],
         margin: [20, 0, 20, 10]
        },

        // To Address
        {
          columns: [
            {
              width: '*',
              stack: [
                { text: 'The Secretary-General', style: 'toHeader', margin: [40, 10, 10, 0] },
                { text: 'Rajya Sabha Secretariat', style: 'toBody' , margin: [40, 10, 10, 0]},
                { text: 'Parliament House, New Delhi', style: 'toBody', margin: [40, 5, 30, 30] }
              ]
            }
          ],
        },

        // Salutation
        { text: 'Sir/Madam,', style: 'salutation',    margin: [40, 0, 0, 10]  },

        // Body paragraphs
        { 
          text: `I have recently purchased certain electronic products [${claimData.value.itemName}] for official use as a Member of Rajya Sabha amounting to Rs. [${claimData.value.claimAmount}].`, 
          style: 'bodyText', 
          margin: [40, 0, 40, 10] 
        },
        { 
          text: 'Kindly find enclosed the relevant purchase bills/invoices for your perusal.', 
          style: 'bodyText', 
          margin: [40, 0, 40, 10] 
        },
        { 
          text: 'It is requested to process the reimbursement as per the applicable rules and guidelines.', 
          style: 'bodyText', 
          margin: [40, 0, 40, 10] 
        },
         { text: 'Thank you.', style: 'closing',  margin: [40, 30, 40, 10]  },

        // Closing
        { 
          columns: [
            { width: '*', text: '' },
            {
              width: 'auto',
              alignment: 'right',
              stack: [
                { text: 'Sincerely,', style: 'closing',   margin: [40, 0, 40, 20]  },
                { text: claimData.value.memberName, style: 'signature',  margin: [40, 0, 40, 20]  },
                { text: '(Member, DS)', style: 'ds',  margin: [40, 0, 40, 20]  }
              ]
            }
          ]
        },

        // Bottom image with space
        {
          image: imagesLoaded.value ? 'bottom-line.png' : null,
          width: 600, // Adjusted to fit within margins
          height: 35,
          margin: [0, 188, 0, 0] // Adjusted space below bottom line to match design
        }
      ],

      styles: {
        memberName: {
          fontSize: 12,
          bold: true,
          color: '#333333',
        },
        memberTitle: {
          fontSize: 12,
          color: '#666666',
          italics: true,
        },
        state: {
          fontSize: 12,
          color: '#666666',
        },
        committee: {
          fontSize: 12,
          color: '#666666',
        },
        address: {
          fontSize: 12,
          color: '#666666',
        },
        contact: {
          fontSize: 12,
          color: '#666666',
        },
        refNum: {
          fontSize: 12,
          color: '#333333',
        },
        date: {
          fontSize: 12,
          color: '#333333',
        },
        toHeader: {
          fontSize: 12,
          bold: true,
          color: '#333333',
        },
        toBody: {
          fontSize: 12,
          bold: true,
          color: '#666666',
        },
        salutation: {
          fontSize: 12,
          color: '#333333',
        },
        bodyText: {
          fontSize: 13,
          color: '#333333',
          lineHeight: 1.3,
        },
        closing: {
          fontSize: 11,
          color: '#666666'
        },
        signature: {
          fontSize: 12,
          bold: true,
          color: '#333333'
        },
        ds: {
          fontSize: 12,
          color: '#666666',
          italics: true
        }
      },

      defaultStyle: {
        font: 'Roboto'
      }
    }

    pdfMake.createPdf(docDefinition).download(fileName)
  } catch (error) {
    console.error('Error generating PDF:', error.message)
    alert('Failed to generate PDF. Please try again.')
  } finally {
    loading.value = false
  }
}

const formatDateForFileName = (date) => {
  return date.toISOString().split('T')[0]
}
</script>

<style scoped>
.reimbursement-claim-generator {
  padding: 20px;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 200px;
}

.generate-btn {
  background-color: #007bff;
  color: white;
  border: none;
  padding: 12px 24px;
  font-size: 16px;
  border-radius: 6px;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.generate-btn:hover:not(:disabled) {
  background-color: #0056b3;
}

.generate-btn:disabled {
  background-color: #6c757d;
  cursor: not-allowed;
}
</style>