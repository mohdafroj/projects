<template>
  <div class="claim-receipt-generator">

  </div>
</template>

<script setup>
import { ref, onMounted, defineEmits, defineExpose  } from 'vue'
// import pdfMake from "pdfmake/build/pdfmake";

// Define emits
const emit = defineEmits(['pdf-generated'])

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
      pdfMake.vfs = {}
    }
    const prefixPath = import.meta.env.VITE_BASE_PATH;
    const topImagePath = prefixPath + '/assets/images/it-claim/top-curve.png'; // Fixed path
    const bottomImagePath = prefixPath + '/assets/images/it-claim/bottom-line.png'; // Fixed path
    const logoPath = prefixPath + '/assets/images/it-claim/logo.png'; // Fixed path
    const blueLogo = prefixPath + '/assets/images/it-claim/blue-logo.png'; // Fixed path
    const watermarkPath = prefixPath + '/assets/images/it-claim/watermark.png'; // Fixed path

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
      } catch (error) {
        console.warn(`Failed to load image at ${path}:`, error.message);
        throw error; // Rethrow to track failures
      }
    };

    const imagePromises = [
      loadImage(topImagePath, 'top-curve.png'),
      loadImage(bottomImagePath, 'bottom-line.png'),
      loadImage(logoPath, 'logo.png'),
      loadImage(blueLogo, 'blue-logo.png'),
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
    console.error('Error loading images:', error.message)
    imagesLoaded.value = false
  }
}

const blobToBase64 = (blob) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onloadend = () => resolve(reader.result)
    reader.onerror = reject
    reader.readAsDataURL(blob)
  })
}

// Define props to receive claim data
const props = defineProps({
  claimData: {
    type: Object,
    required: true,
    default: () => ({
      claimId: 'MMMMYY0001',
      originalForReceipt: 'Original for receipt',
      organization: 'Rajya Sabha',
      address: 'Address line, Street Address, City Name, State,\nCountry — Pin Code',
      email: 'username@email.com',
      phone: '+91 0000000000',
      processedBy: 'P A Name',
      processedOn: 'DD-MM-YYYY',
      approvedBy: 'Member Name',
      systemIP: '10.110.100.21',
      deviceName: 'Acer laptop',
      items: [
        {
          sno: 1,
          itemName: 'HP x1000 Wired Optical Mouse',
          qty: 4,
          unitPrice: 250.00,
          total: 1000.00
        }
      ],
      totalAmount: 27516.00,
      claimReceivedBy: ''
    })
  }
})

const generatePDFBlob = async () => {
  // if (!pdfMake) {
  //   alert('PDF library is not loaded yet. Please try again.')
  //   return
  // }

  loading.value = true

  try { 
    //  items table data
    const tableBody = [
      // Header row
      [
        { text: 'S/No.', style: 'tableHeader' },
        { text: 'Item Name', style: 'tableHeader' },
        { text: 'Qty', style: 'tableHeader' },
        { text: 'Unit Price', style: 'tableHeader' },
        { text: 'Total', style: 'tableHeader' }
      ],
      // Data rows
      ...props.claimData.items.map(item => [
        { text: item.sno.toString(), style: 'tableCell' },
        { text: item.itemName, style: 'tableCell' },
        { text: item.qty.toString(), style: 'tableCell', alignment: 'center' },
        { text: `₹${item.unitPrice.toFixed(2)}`, style: 'tableCell', alignment: 'right' },
        { text: `₹${item.total.toFixed(2)}`, style: 'tableCell', alignment: 'right' }
      ])
    ]
    const totalProducts = props.claimData.items.length;
    let itemNames = 'product ';
    if ( totalProducts == 1  ){
      itemNames = itemNames + props.claimData.items[0]['itemName'];
    } else {
      itemNames = 'products ';
      props.claimData.items.map((item, index) => { 
        if ( totalProducts == index + 1  ) {          
          itemNames = itemNames.slice(0, -2) + ' and ' + item.itemName;
        } else {
          itemNames = itemNames + item.itemName + ', ';
        }
      });
    }
    const standardMargin = [40, 10, 40, 10]; // left, top, right, bottom
    const docDefinition = {
      pageSize: 'A4',
      pageMargins: [0, 0, 0, 0],
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
            },
            // Member details header
            {
              columns: [
                {
                  width: '40%',
                  stack: [
                    { text: props.claimData.approvedBy, style: 'memberName', margin: [20, 40, 20, 10] },
                    { text: 'Member (Rajya Sabha)', style: 'memberTitle',margin: [20, 0, 20, 10] },
                    { text: props.claimData?.state || '', style: 'state',margin: [20, 0, 20, 10] },
                    { text: props.claimData?.committee ? `Member of committee: ${props.claimData.committee}` : '', style: 'committee',margin: [20, 0, 20, 10] }
                  ]
                },
                {
                  width: '60%',
                  alignment: 'right',
                  stack: [
                    { text: props.claimData?.address ? `Address: ${props.claimData.address}` : '', style: 'address', margin: [20, 20, 20, 10] },
                    { text: props.claimData?.email ? `Email: ${props.claimData.email}` : '', style: 'contact', margin: [20, 0, 20, 10] },
                    { text: props.claimData?.mobile ? `Mob: ${props.claimData.mobile}` : '', style: 'contact', margin: [20, 0, 20, 10] },
                    { text: props.claimData?.phone ? `Tel: ${props.claimData.phone}` : '', style: 'contact', margin: [20, 0, 20, 10],},
                { 
                  width: '*', 
                  text: `Reference Number: ${props.claimData.claimId}`, 
                  style: 'refNum' ,
                  margin: [20, 0, 20, 10]
                },
                { 
                  width: 'auto', 
                  text: `Date: ${props.claimData.processedOn}`, 
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
              text: `I have recently purchased certain electronic ${itemNames} for official use as a Member of Rajya Sabha amounting to Rs. ${props.claimData.totalAmount}.`, 
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
                    { text: props.claimData.approvedBy, style: 'signature',  margin: [40, 0, 40, 20]  },
                    { text: props.claimData.memberDS || '',  margin: [40, 0, 40, 20]  }
                  ]
                }
              ]
            },
            // Bottom image with space
            {
              image: imagesLoaded.value ? 'bottom-line.png' : null,
              width: 600, // Adjusted to fit within margins
              height: 35,
              absolutePosition: { x: 0, y: 810 }
            },

          ],
          pageBreak: 'after'
        },



        { text: '', margin: [0, 10] }, // Spacer only

        // Header section
        {
          columns: [
            {
              width: '*',
              stack: [
                { text: 'Claim Receipt', style: 'header', margin: [0, 10] },
                { text: `Claim ID: ${props.claimData.claimId}`, style: 'claimId', margin: [0, 10] },
                { text: props.claimData.originalForReceipt, style: 'originalText', margin: [0, 10] }
              ]
            },
            {
              width: 'auto',
              stack: [
                {
                  columns: [
                    {
                      image: 'blue-logo.png',
                      width: 45,
                      height: 45,
                      margin: [0, -10, 0, 0]
                    },
                    {
                      stack: [
                        { text: props.claimData.organization, style: 'organizationName' },
                        { text: props.claimData.address, style: 'address', margin: [0, 2, 0, 0] },
                        { text: props.claimData.email, style: 'contact', margin: [0, 2, 0, 0] },
                        { text: props.claimData.phone, style: 'contact', margin: [0, 2, 0, 0] }
                      ]
                    }
                  ],
                  alignment: 'right',
                  columnGap: 5 
                }
              ]
            }
          ],
          margin: standardMargin
        },
        
        // Claim Details 
        {
          text: 'Claim Details',
          style: 'sectionHeader',
          margin: standardMargin
        },
        {
          columns: [
            {
              width: '*',
              stack: [
                {
                  text: `Processed by: ${props.claimData.processedBy}`,
                  style: 'claimDetail'
                },
                {
                  text: `Processed on: ${props.claimData.processedOn}`,
                  style: 'claimDetail'
                },
                {
                  text: `Approved by: ${props.claimData.approvedBy}`,
                  style: 'claimDetail'
                }
              ]
            },
            {
              width: 'auto',
              stack: [
                {
                  text: `System IP: ${props.claimData.systemIP}`,
                  style: 'claimDetail',
                  alignment: 'right'
                },
                {
                  text: `Device Name: ${props.claimData.deviceName}`,
                  style: 'claimDetail',
                  alignment: 'right'
                }
              ]
            }
          ]
        },

        { text: '', margin: [0, 10] }, // Spacer

        // Items Table
        {
          table: {
            headerRows: 1,
            widths: [30, '*', 50, 70, 70],
            body: tableBody
          },
          layout: {
            hLineWidth: function (i, node) {
              return (i === 0 || i === 1 || i === node.table.body.length) ? 1 : 0.5
            },
            vLineWidth: function (i, node) {
              return (i === 0 || i === node.table.widths.length) ? 1 : 0.5
            },
            hLineColor: function (i, node) {
              return '#cccccc'
            },
            vLineColor: function (i, node) {
              return '#cccccc'
            },
            paddingLeft: function(i) { return 8 },
            paddingRight: function(i) { return 8 },
            paddingTop: function(i) { return 8 },
            paddingBottom: function(i) { return 8 }
          },
          margin: standardMargin
        },

        { text: '', margin: [0, 20] }, // Spacer

        // Total Amount
        {
          columns: [
            { width: '*', text: '' },
            {
              width: 'auto',
              table: {
                widths: [100, 100],
                body: [
                  [
                    { text: 'Total Amount', style: 'totalLabel' },
                    { text: `₹${props.claimData.totalAmount.toFixed(2)}`, style: 'totalLabel' }
                  ]
                ]
              },
              layout: {
                hLineWidth: function () { return 0 },
                vLineWidth: function () { return 0 },
                hLineColor: function () { return '#333333' },
                vLineColor: function () { return '#333333' },
                paddingLeft: function() { return 10 },
                paddingRight: function() { return 10 },
                paddingTop: function() { return 10 },
                paddingBottom: function() { return 10 }
              }
            }
          ],
          margin: standardMargin
        },

        { text: '', margin: [0, 40] }, // Spacer

        // Footer
        {
          text: 'Claim received by: ' + props.claimData.claimReceivedBy,
          style: 'footer',
          margin: standardMargin,
          alignment: 'right'
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
        header: {
          fontSize: 14,
          bold: true,
          color: '#333333',
        },
        claimId: {
          fontSize: 11,
          color: '#666666',
        },
        originalText: {
          fontSize: 11,
          color: '#666666',
        },
        organizationName: {
          fontSize: 20,
          bold: true,
          color: '#333333',
          margin: [0, 0, 0, 12]
        },
        sectionHeader: {
          fontSize: 14,
          bold: true,
          color: '#333333',
        },
        claimDetail: {
          fontSize: 11,
          color: '#333333',
          margin: [40, 0, 40, 15]
        },
        tableHeader: {
          fontSize: 11,
          bold: true,
          color: '#333333',
          fillColor: '#f5f5f5',
          alignment: 'center'
        },
        tableCell: {
          fontSize: 10,
          color: '#333333'
        },
        totalLabel: {
          fontSize: 14,
          bold: true,
          color: '#333333',
          alignment: 'right'
        },
        totalAmount: {
          fontSize: 16,
          bold: true,
          color: '#333333',
          alignment: 'right'
        },
        footer: {
          fontSize: 11,
          color: '#666666'
        }
      },

      defaultStyle: {
        font: 'Roboto'
      }
    }

    // Create PDF and get blob
    const pdfDocGenerator = pdfMake.createPdf(docDefinition)
    
    pdfDocGenerator.getBlob((blob) => {
      // Emit the blob to parent component
      emit('pdf-generated', blob)
      loading.value = false
    })
    
  } catch (error) {
    console.error('Error generating PDF:', error.message)
    // alert('Failed to generate PDF. Please try again.')
    loading.value = false
  }
}

defineExpose({
  generatePDFBlob
})
const formatDateForFileName = (date) => {
  return date.toISOString().split('T')[0]
}
</script>

<style scoped>
.claim-receipt-generator {
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