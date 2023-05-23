import axios from 'axios';
import { Session } from 'next-auth';

export function axiosInstance(session: Session | any) {
  return axios.create({
    baseURL: 'https://classroom.googleapis.com/v1',
    headers: {
      Authorization: `Bearer ${session?.accessToken}`,
    },
    params: {
      key: process.env.NEXT_PUBLIC_GOOGLE_API_KEY,
    },
  });
}
