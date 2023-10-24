import googleClassroom from '@/lib/config/googleClassroom';
import { Avatar, NavLink } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import React from 'react';

type Props = {
  students: classroom_v1.Schema$Student[] | undefined;
  submissions: classroom_v1.Schema$StudentSubmission[] | undefined;
};

export default async function StudentList({ students }: Props) {
  const student = students?.[0];
  return (
    <nav>
      {students?.map((it) => (
        <NavLink
          key={it.userId}
          label={it.profile?.name?.fullName}
          leftSection={<Avatar src={it.profile?.photoUrl} />}
        />
      ))}
    </nav>
  );
}
