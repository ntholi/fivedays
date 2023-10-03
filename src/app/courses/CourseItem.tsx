'use client';
import { ActionIcon, Card, Flex, Image, Text } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import Link from 'next/link';
import React from 'react';
import { IconExternalLink } from '@tabler/icons-react';
import { useRouter } from 'next/navigation';
import classes from './CourseItem.module.css';

type Props = {
  course: classroom_v1.Schema$Course;
};

export default function CourseItem({ course }: Props) {
  const router = useRouter();
  return (
    <Card
      shadow='sm'
      padding='lg'
      radius='sm'
      className={classes.card}
      withBorder
      onClick={() => {
        router.push(`/courses/${course.id}`);
      }}
    >
      <Card.Section>
        <Image
          src='https://images.unsplash.com/photo-1579227114347-15d08fc37cae?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=2550&q=80'
          h={160}
          alt='No way!'
        />
      </Card.Section>
      <Flex justify='space-between' mt='lg'>
        <Text fw={500} size='lg'>
          {course.name}
        </Text>
        <ActionIcon
          variant='subtle'
          color='gray'
          component={Link}
          href={course.alternateLink || ''}
          onClick={(event) => {
            event.stopPropagation();
          }}
          target='_blank'
        >
          <IconExternalLink size={'1rem'} />
        </ActionIcon>
      </Flex>
      <Flex mt='sm' justify='space-between'>
        <Text mt='xs' c='dimmed' size='sm'>
          {course.description}
        </Text>
        <Text mt='xs' c='dimmed' size='sm'>
          {course.section}
        </Text>
      </Flex>
    </Card>
  );
}
