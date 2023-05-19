import { NavLink, ScrollArea } from '@mantine/core';
import React from 'react';

type Props = {
  submissions: StudentSubmission[];
  setActive: (id: StudentSubmission) => void;
  active: StudentSubmission | null;
};

export default function StudentList({ submissions, active, setActive }: Props) {
  return (
    <ScrollArea h="90vh">
      {submissions.map((it) => (
        <NavLink
          key={it.id}
          label={it.studentName}
          active={it.id === active?.id}
          onClick={() => setActive(it)}
          variant="filled"
        />
      ))}
    </ScrollArea>
  );
}
