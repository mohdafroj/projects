/**
 * Deep clone an object
 * @param {Object} obj - Object to clone
 * @returns {Object} - Cloned object
 */
export const deepClone = (obj) => {
    return JSON.parse(JSON.stringify(obj))
  }
  
  /**
   * Format currency
   * @param {Number} value - Value to format
   * @returns {String} - Formatted currency string
   */
  export const formatCurrency = (value) => {
    return `$${value.toLocaleString()}`
  }
  
  /**
   * Parse currency string to number
   * @param {String} value - Currency string
   * @returns {Number} - Parsed number
   */
  export const parseCurrency = (value) => {
    return parseInt(value.replace(/[^0-9]/g, ''))
  }
  
  /**
   * Generate dummy data for testing
   * @returns {Array} - Sample hierarchical data
   */
  export const generateDummyData = () => {
    return [
      {
        id: '1',
        name: 'Marketing Department',
        budget: '$1,200,000',
        employees: 45,
        expanded: true,
        subRows: [
          {
            id: '1-1',
            name: 'Digital Marketing Team',
            budget: '$450,000',
            employees: 18,
            expanded: false,
            subRows: [
              {
                id: '1-1-1',
                name: 'Social Media Division',
                budget: '$150,000',
                employees: 8,
                expanded: false,
                subRows: [
                  {
                    id: '1-1-1-1',
                    name: 'Instagram Campaign',
                    budget: '$35,000',
                    employees: 2,
                    expanded: false,
                    subRows: []
                  },
                  {
                    id: '1-1-1-2',
                    name: 'TikTok Campaign',
                    budget: '$42,000',
                    employees: 3,
                    expanded: false,
                    subRows: []
                  }
                ]
              },
              {
                id: '1-1-2',
                name: 'SEO Division',
                budget: '$120,000',
                employees: 5,
                expanded: false,
                subRows: [
                  {
                    id: '1-1-2-1',
                    name: 'On-Page Optimization',
                    budget: '$65,000',
                    employees: 3,
                    expanded: false,
                    subRows: []
                  }
                ]
              }
            ]
          },
          {
            id: '1-2',
            name: 'Traditional Marketing Team',
            budget: '$350,000',
            employees: 12,
            expanded: false,
            subRows: [
              {
                id: '1-2-1',
                name: 'Print Media Division',
                budget: '$125,000',
                employees: 4,
                expanded: false,
                subRows: [
                  {
                    id: '1-2-1-1',
                    name: 'Magazine Campaign',
                    budget: '$85,000',
                    employees: 2,
                    expanded: false,
                    subRows: []
                  }
                ]
              }
            ]
          }
        ]
      },
      {
        id: '2',
        name: 'Engineering Department',
        budget: '$1,800,000',
        employees: 65,
        expanded: true,
        subRows: [
          {
            id: '2-1',
            name: 'Frontend Team',
            budget: '$650,000',
            employees: 24,
            expanded: false,
            subRows: [
              {
                id: '2-1-1',
                name: 'Vue Development',
                budget: '$320,000',
                employees: 12,
                expanded: false,
                subRows: [
                  {
                    id: '2-1-1-1',
                    name: 'Dashboard Project',
                    budget: '$125,000',
                    employees: 4,
                    expanded: false,
                    subRows: []
                  },
                  {
                    id: '2-1-1-2',
                    name: 'Mobile App Project',
                    budget: '$195,000',
                    employees: 8,
                    expanded: false,
                    subRows: []
                  }
                ]
              }
            ]
          },
          {
            id: '2-2',
            name: 'Backend Team',
            budget: '$720,000',
            employees: 26,
            expanded: false,
            subRows: [
              {
                id: '2-2-1',
                name: 'API Development',
                budget: '$380,000',
                employees: 14,
                expanded: false,
                subRows: [
                  {
                    id: '2-2-1-1',
                    name: 'Authentication Service',
                    budget: '$145,000',
                    employees: 5,
                    expanded: false,
                    subRows: []
                  }
                ]
              }
            ]
          }
        ]
      }
    ]
  }
  
  /**
   * Get default filter options based on data
   * @param {Array} data - Table data
   * @returns {Object} - Filter options grouped by field
   */
  export const getFilterOptions = (data) => {
    return {
      name: ['Marketing', 'Engineering', 'Team', 'Division', 'Campaign', 'Project'],
      budget: ['< $100,000', '$100,000 - $500,000', '> $500,000'],
      employees: ['< 10', '10 - 30', '> 30']
    }
  }
  
  /**
   * Validate a filter operation
   * @param {String} field - Field to filter on
   * @param {String} operator - Operator to use
   * @param {any} value - Value to filter with
   * @param {Object} row - Row to test
   * @returns {Boolean} - Whether the row passes the filter
   */
  export const validateFilter = (field, operator, value, row) => {
    if (field === 'name') {
      const rowValue = row.name.toLowerCase()
      
      if (operator === 'contains') {
        return rowValue.includes(value.toLowerCase())
      }
      if (operator === 'equals') {
        return rowValue === value.toLowerCase()
      }
      if (operator === 'starts') {
        return rowValue.startsWith(value.toLowerCase())
      }
    }
    
    if (field === 'budget') {
      const rowValue = parseCurrency(row.budget)
      const compareValue = typeof value === 'string' ? parseCurrency(value) : value
      
      if (operator === 'lt') {
        return rowValue < compareValue
      }
      if (operator === 'gt') {
        return rowValue > compareValue
      }
      if (operator === 'between') {
        return rowValue >= value[0] && rowValue <= value[1]
      }
    }
    
    if (field === 'employees') {
      const rowValue = row.employees
      
      if (operator === 'lt') {
        return rowValue < value
      }
      if (operator === 'gt') {
        return rowValue > value
      }
      if (operator === 'between') {
        return rowValue >= value[0] && rowValue <= value[1]
      }
    }
    
    return false
  }