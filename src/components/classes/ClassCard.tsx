import { Card, Title, createStyles, rem } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import Link from 'next/link';
import React from 'react';

type Props = {
  item: classroom_v1.Schema$Course;
};

export default function ClassCard({ item }: Props) {
  const { classes } = useStyles();

  return (
    <Link
      style={{ textDecoration: 'none', color: 'inherit' }}
      href={{ pathname: `/classes/${item.id}`, query: { name: item.name } }}
    >
      <Card withBorder radius='md' mih={150} className={classes.card}>
        <Title order={3} fw='normal'>
          {item.name}
        </Title>
      </Card>
    </Link>
  );
}

const useStyles = createStyles((theme) => ({
  card: {
    transition: 'box-shadow 0.3s ease, border-color 0.3s ease',
    '&:hover': {
      boxShadow: `${rem(0)} ${rem(2)} ${rem(4)} ${
        theme.colorScheme === 'dark'
          ? theme.colors.dark[4]
          : theme.colors.gray[6]
      }`,
      cursor: 'pointer',
    },
  },
}));
