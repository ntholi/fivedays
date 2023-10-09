import React from 'react';
import { Container } from '@mantine/core';
import RubricForm from './RubricForm';
import prisma from '@/lib/db';

type Props = {
  params: {
    courseId: string;
    id: string;
  };
};

export default async function RubricPage({ params: { id, courseId } }: Props) {
  const rubricItems = await prisma.rubricItem.findMany({
    where: {
      courseId: courseId,
      courseWorkId: id,
    },
  });

  return (
    <Container mt='xl'>
      <RubricForm />

      <ul>
        {rubricItems.map((it) => (
          <li key={it.id}>{it.title}</li>
        ))}
      </ul>
    </Container>
  );
}
