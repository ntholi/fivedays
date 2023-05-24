import { Group, NavLink, ScrollArea, Text } from '@mantine/core';
import React from 'react';
import { Grade } from './GraderPanel';

type Props = {
  submissions: StudentSubmission[];
  setActive: (id: StudentSubmission) => void;
  active: StudentSubmission | null;
  maxPoints: number;
  setGrades: (grades: Grade[]) => void;
};

export default function StudentList({
  submissions,
  active,
  setActive,
  maxPoints,
  setGrades,
}: Props) {
  function updateSelected(submission: StudentSubmission) {
    setGrades([]);
    setActive(submission);
  }

  return (
    <ScrollArea h='90vh'>
      {submissions.map((it) => (
        <NavLink
          key={it.id}
          label={
            <Group position='apart'>
              <Text>{it.studentName}</Text>
              <Text>
                {it.draftGrade ?? '?'}/{maxPoints}
              </Text>
            </Group>
          }
          active={it.id === active?.id}
          onClick={() => updateSelected(it)}
          variant='filled'
        />
      ))}
    </ScrollArea>
  );
}
