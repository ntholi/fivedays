'use client';
import { RootState } from '@/lib/redux/store';
import { setStudentSubmission } from '@/lib/redux/submissionSlice';
import { Avatar, NavLink } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import React from 'react';
import { useDispatch, useSelector } from 'react-redux';

type Props = {
  students: classroom_v1.Schema$Student[] | undefined;
  submissions: classroom_v1.Schema$StudentSubmission[] | undefined;
};

export default function StudentList({ students }: Props) {
  const dispatch = useDispatch();
  return (
    <nav>
      {students?.map((it) => (
        <NavLink
          key={it.userId}
          label={it.profile?.name?.fullName}
          leftSection={<Avatar src={getProfileUrl(it)} size='sm' />}
          onClick={() => {
            dispatch(
              setStudentSubmission({
                student: it,
                submission: null,
              })
            );
          }}
        />
      ))}
    </nav>
  );
}

const getProfileUrl = (student: classroom_v1.Schema$Student) => {
  const url = student.profile?.photoUrl;
  if (!url) return undefined;
  if (url.startsWith('//')) {
    return `https:${url}`;
  }
  return url;
};
