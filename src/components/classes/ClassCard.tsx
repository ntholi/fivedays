import { Card, Title } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import React from 'react';

type Props = {
  item: classroom_v1.Schema$Course;
};
export default function ClassCard({ item }: Props) {
  return (
    <Card withBorder radius='md' mih={120}>
      <Title order={3} fw='normal'>
        {item.name}
      </Title>
    </Card>
  );
}
