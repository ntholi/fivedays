import { classroom_v1 } from 'googleapis';

export const formatDate = (
  date?: classroom_v1.Schema$Date,
  time?: classroom_v1.Schema$TimeOfDay
): string => {
  if (!date || !time) return '';
  const { day, month, year } = date;
  const { hours, minutes } = time;
  return `${day}/${month}/${year} ${hours}:${minutes}`;
};
