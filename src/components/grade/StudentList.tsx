import { Group, NavLink, ScrollArea, Text } from '@mantine/core';
import React from 'react';

type Props = {
  submissions: StudentSubmission[];
  setActive: (id: StudentSubmission) => void;
  active: StudentSubmission | null;
  maxPoints: number;
};

export default function StudentList({
  submissions,
  active,
  setActive,
  maxPoints,
}: Props) {
  return (
    <ScrollArea h="90vh">
      {submissions.map((it) => (
        <NavLink
          key={it.id}
          label={
            <Group position="apart">
              <Text>{it.studentName}</Text>
              <Text>
                {it.draftGrade ?? '?'}/{maxPoints}
              </Text>
            </Group>
          }
          active={it.id === active?.id}
          onClick={() => setActive(it)}
          variant="filled"
        />
      ))}
    </ScrollArea>
  );
}
