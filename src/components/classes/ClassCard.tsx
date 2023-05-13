import { Card, Title } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import { useRouter } from 'next/router';
import React, { use } from 'react';

type Props = {
  item: classroom_v1.Schema$Course;
};
export default function ClassCard({ item }: Props) {
  const router = useRouter();

  function openClassroom() {
    router.push({
      pathname: `/classes/${item.id}`,
      query: { name: item.name },
    });
  }

  return (
    <Card
      withBorder
      radius='md'
      mih={150}
      onClick={openClassroom}
      sx={{ cursor: 'pointer' }}
    >
      <Title order={3} fw='normal'>
        {item.name}
      </Title>
    </Card>
  );
}
