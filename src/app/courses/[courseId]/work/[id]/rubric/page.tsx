import React from 'react';
import { Container } from '@mantine/core';
import RubricForm from './RubricForm';
import prisma from '@/lib/prisma';

type Props = {
  params: {
    id: string;
  };
};

export default async function RubricPage({ params: { id } }: Props) {
  const rubric = await prisma.rubric.findUnique({
    where: {
      courseWorkId: id,
    },
    include: {
      rubricItems: true,
    },
  });

  return (
    <Container mt='xl'>
      <RubricForm />

      <ul>
        {rubric?.rubricItems.map((it) => (
          <li key={it.id}>{it.title}</li>
        ))}
      </ul>
    </Container>
  );
}
