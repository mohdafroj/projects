export function deepClone(ob = {}) {
  return JSON.parse(JSON.stringify(ob));
}

export function formatDate(date, options, locale = 'en-IN') {
  if (!date) return '';
  try {
    let defaultOptions = {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
      year: 'numeric',
    };
    if (typeof options === 'object') defaultOptions = options;
    return new Intl.DateTimeFormat(locale, defaultOptions).format(
      new Date(date)
    );
  } catch {
    return '';
  }
}