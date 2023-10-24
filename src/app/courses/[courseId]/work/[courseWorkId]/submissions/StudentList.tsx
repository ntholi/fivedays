import { Avatar, NavLink } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import React from 'react';

type Props = {
  students: classroom_v1.Schema$Student[] | undefined;
  submissions: classroom_v1.Schema$StudentSubmission[] | undefined;
};

export default async function StudentList({ students }: Props) {
  return (
    <nav>
      {students?.map((it) => (
        <NavLink
          key={it.userId}
          label={it.profile?.name?.fullName}
          leftSection={<Avatar src={getProfileUrl(it)} size='sm' />}
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
