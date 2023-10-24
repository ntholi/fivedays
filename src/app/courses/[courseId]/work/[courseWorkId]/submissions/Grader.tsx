'use client';
import { RootState } from '@/lib/redux/store';
import axios from 'axios';
import { useSession } from 'next-auth/react';
import React from 'react';
import { useSelector } from 'react-redux';

export default function Grader() {
  const session = useSession();
  const state = useSelector(
    (state: RootState) => state.studentSubmission.value
  );
  return <div>Grades Here</div>;
}

export async function readFile(fileId: string, session: any) {
  const fileUrl = `https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`;
  const response = await axios.get(fileUrl, {
    headers: { Authorization: `Bearer ${session.accessToken}` },
    responseType: 'text',
  });
  return response.data;
}
