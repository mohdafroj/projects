const CHIEF_DOCUMENT = /^chief:\d+:(en|hi)$/
const JS_DOCUMENT = /^js:\d+$/

export function assertValidDocumentName(name: string): void {
  if (!CHIEF_DOCUMENT.test(name) && !JS_DOCUMENT.test(name)) {
    throw new Error('Invalid realtime document name')
  }
}
